//! Runs tools in containers.
//!
//! Killing the `docker run` client does not stop the container it started, so every container
//! gets a unique name and is removed by name when it times out. Every container is also labelled
//! with the run's ID, so a cancelled run can remove whatever it left behind.

use std::ffi::OsString;
use std::path::{Path, PathBuf};
use std::sync::atomic::{AtomicU64, Ordering};
use std::time::Duration;

use tracing::{debug, warn};

use super::process::{Completed, Invocation, ProcessError};
use crate::config::{Containers, PullPolicy};

/// Time allowed for quick runtime commands: version, inspect, remove.
const CONTROL_TIMEOUT: Duration = Duration::from_mins(1);
/// Time allowed for pulling an image.
const PULL_TIMEOUT: Duration = Duration::from_mins(30);

#[derive(Debug)]
pub struct Runtime {
    binary: PathBuf,
    label: String,
    run_id: String,
    next: AtomicU64,
}

/// One container run.
#[derive(Debug, Clone)]
pub struct Spec {
    pub image: String,
    /// Runs as the image's default user when not set.
    pub user: Option<(u32, u32)>,
    pub mounts: Vec<Mount>,
    /// Set in the container, over the image's own values.
    pub env: Vec<(String, String)>,
    pub entrypoint: Option<String>,
    pub args: Vec<OsString>,
    pub timeout: Duration,
}

#[derive(Debug, Clone)]
pub struct Mount {
    pub host: PathBuf,
    pub target: String,
    pub read_only: bool,
}

#[derive(Debug, thiserror::Error)]
pub enum ContainerError {
    #[error(transparent)]
    Process(#[from] ProcessError),
    /// A container that ran and failed: what it ran and what it said, without the long
    /// `docker run` command, which goes to the debug log.
    #[error("{program} in {image} failed ({status}): {stderr}")]
    Failed {
        program: String,
        image: String,
        status: String,
        stderr: String,
    },
    #[error("cannot mount {0:?}: paths containing commas, quotes or newlines are not supported")]
    UnsupportedPath(PathBuf),
    #[error("image {0} is not present and containers.pull is \"never\"")]
    ImageMissing(String),
}

impl Runtime {
    pub fn new(config: &Containers, run_id: impl Into<String>) -> Self {
        Self {
            binary: config.runtime.clone(),
            label: config.label.clone(),
            run_id: run_id.into(),
            next: AtomicU64::new(1),
        }
    }

    fn control(&self, timeout: Duration) -> Invocation {
        Invocation::new(&self.binary, timeout)
    }

    fn run_label(&self) -> String {
        format!("{}.run={}", self.label, self.run_id)
    }

    /// Builds the `docker run` command for a spec. Containers get no network, no capabilities
    /// and no way to gain privileges; none of the tools needs them.
    fn run_invocation(&self, spec: &Spec, name: &str) -> Result<Invocation, ContainerError> {
        let mut invocation = self
            .control(spec.timeout)
            .args(["run", "--rm", "--pull=never", "--network=none"])
            .args(["--cap-drop=ALL", "--security-opt=no-new-privileges"])
            .args(["--name", name])
            .args(["--label", &format!("{}=1", self.label)])
            .args(["--label", &self.run_label()]);
        if let Some((uid, gid)) = spec.user {
            invocation = invocation.args(["--user", &format!("{uid}:{gid}")]);
        }
        for mount in &spec.mounts {
            invocation = invocation.args(["--mount", &mount_option(mount)?]);
        }
        for (name, value) in &spec.env {
            invocation = invocation.args(["--env", &format!("{name}={value}")]);
        }
        if let Some(entrypoint) = &spec.entrypoint {
            invocation = invocation.args(["--entrypoint", entrypoint]);
        }
        Ok(invocation.arg(&spec.image).args(spec.args.iter().cloned()))
    }

    /// Runs a container to completion and removes it.
    pub async fn run(&self, spec: &Spec) -> Result<Completed, ContainerError> {
        let name = format!(
            "{}-{}-{}",
            self.label.replace('.', "-"),
            self.run_id,
            self.next.fetch_add(1, Ordering::Relaxed)
        );
        let invocation = self.run_invocation(spec, &name)?;
        match invocation.run().await {
            Err(error @ ProcessError::TimedOut { .. }) => {
                self.remove(&[name]).await;
                Err(error.into())
            }
            Err(ProcessError::Failed {
                command,
                status,
                stderr,
            }) => {
                debug!("failed: {command}");
                Err(ContainerError::Failed {
                    program: spec
                        .entrypoint
                        .clone()
                        .unwrap_or_else(|| "container".into()),
                    image: spec.image.clone(),
                    status,
                    stderr: stderr.trim().replace('\n', "; "),
                })
            }
            result => Ok(result?),
        }
    }

    /// Removes every container this run started that is still present.
    pub async fn remove_run_containers(&self) {
        let listed = self
            .control(CONTROL_TIMEOUT)
            .args(["ps", "--all", "--quiet", "--filter"])
            .arg(format!("label={}", self.run_label()))
            .run()
            .await;
        match listed {
            Ok(done) => {
                let ids: Vec<String> = done
                    .stdout_text()
                    .split_whitespace()
                    .map(String::from)
                    .collect();
                self.remove(&ids).await;
            }
            Err(error) => warn!("cannot list this run's containers to remove them: {error}"),
        }
    }

    async fn remove(&self, names: &[String]) {
        if names.is_empty() {
            return;
        }
        let removed = self
            .control(CONTROL_TIMEOUT)
            .args(["rm", "--force"])
            .args(names)
            .run()
            .await;
        if let Err(error) = removed {
            warn!("cannot remove containers {names:?}: {error}");
        }
    }

    pub async fn server_version(&self) -> Result<String, ContainerError> {
        let done = self
            .control(CONTROL_TIMEOUT)
            .args(["version", "--format", "{{.Server.Version}}"])
            .run()
            .await?;
        Ok(done.stdout_text().trim().to_owned())
    }

    /// Makes the image available locally, as the pull policy allows.
    pub async fn ensure_image(
        &self,
        image: &str,
        policy: PullPolicy,
    ) -> Result<(), ContainerError> {
        let present = || async {
            self.control(CONTROL_TIMEOUT)
                .args(["image", "inspect", "--format", "{{.Id}}", image])
                .run()
                .await
                .is_ok()
        };
        if policy != PullPolicy::Always && present().await {
            return Ok(());
        }
        if policy == PullPolicy::Never {
            return Err(ContainerError::ImageMissing(image.to_owned()));
        }
        self.pull(image).await
    }

    async fn pull(&self, image: &str) -> Result<(), ContainerError> {
        self.control(PULL_TIMEOUT)
            .args(["pull", "--quiet", image])
            .run()
            .await?;
        Ok(())
    }
}

/// `--mount` takes comma-separated fields, so a path with a comma or quote cannot be passed
/// safely. `-v` would be worse: it splits on colons.
fn mount_option(mount: &Mount) -> Result<String, ContainerError> {
    let host = checked_path(&mount.host)?;
    let target = checked_path(Path::new(&mount.target))?;
    let mut option = format!("type=bind,source={host},target={target}");
    if mount.read_only {
        option.push_str(",readonly");
    }
    Ok(option)
}

fn checked_path(path: &Path) -> Result<&str, ContainerError> {
    path.to_str()
        .filter(|s| !s.contains([',', '"', '\n']))
        .ok_or_else(|| ContainerError::UnsupportedPath(path.to_path_buf()))
}

#[cfg(test)]
mod tests {
    use super::*;

    fn runtime() -> Runtime {
        Runtime::new(&Containers::default(), "1700000000-42")
    }

    fn spec() -> Spec {
        Spec {
            image: "tesseract@sha256:00".into(),
            user: Some((1000, 1000)),
            mounts: vec![
                Mount {
                    host: "/mnt/nbnp/The_News/TN_1909".into(),
                    target: "/in".into(),
                    read_only: true,
                },
                Mount {
                    host: "/work/ocr".into(),
                    target: "/out".into(),
                    read_only: false,
                },
            ],
            env: vec![("OMP_THREAD_LIMIT".into(), "1".into())],
            entrypoint: Some("tesseract".into()),
            args: vec!["/in/a.jpg".into(), "/out/a".into()],
            timeout: Duration::from_mins(15),
        }
    }

    #[test]
    fn run_command() {
        let invocation = runtime().run_invocation(&spec(), "c-1").unwrap();
        assert_eq!(
            invocation.to_string(),
            "docker run --rm --pull=never --network=none --cap-drop=ALL \
             --security-opt=no-new-privileges --name c-1 \
             --label ca.unb.lib.nbnp-ingest=1 --label ca.unb.lib.nbnp-ingest.run=1700000000-42 \
             --user 1000:1000 \
             --mount type=bind,source=/mnt/nbnp/The_News/TN_1909,target=/in,readonly \
             --mount type=bind,source=/work/ocr,target=/out \
             --env OMP_THREAD_LIMIT=1 \
             --entrypoint tesseract tesseract@sha256:00 /in/a.jpg /out/a"
        );
    }

    #[test]
    fn unsafe_mount_paths_are_refused() {
        let mut spec = spec();
        spec.mounts[0].host = "/mnt/nbnp/a,target=/etc".into();
        assert!(matches!(
            runtime().run_invocation(&spec, "c-1"),
            Err(ContainerError::UnsupportedPath(_))
        ));
    }
}
