//! `config show` and `config check`.

use std::fs;
use std::io::ErrorKind;
use std::path::{Path, PathBuf};
use std::time::Duration;

use crate::adapters::container::{Mount, Runtime, Spec};
use crate::config::{Config, Loaded, SiteCredentials};
use crate::error::{Error, Outcome};
use crate::report::{Check, Status, format_checks};

/// Time allowed for the short tool runs these checks make.
const PROBE_TIMEOUT: Duration = Duration::from_mins(2);

pub fn show(loaded: &Loaded) -> Outcome {
    let text = toml::to_string_pretty(&loaded.config)
        .unwrap_or_else(|error| format!("# cannot format the configuration: {error}\n"));
    print!("# Loaded from {}\n\n{text}", loaded.path.display());
    Outcome::Clean
}

pub async fn check(loaded: &Loaded, run_id: &str) -> Result<Outcome, Error> {
    let config = &loaded.config;
    let runtime = Runtime::new(&config.containers, run_id);

    let mut checks = vec![
        Check::ok("config file", loaded.path.display().to_string()),
        Check::from_result(
            "site credentials",
            SiteCredentials::load(&config.site.credentials_file)
                .map(|c| format!("user {}", c.username)),
        ),
        Check::from_result("public files", public_files(&config.paths.public_files)),
        Check::from_result("source root", readable_dir(&config.paths.source_root)),
        Check::from_result(
            "work dir",
            writable_dir(&config.paths.work_dir, run_id)
                .and_then(|()| free_space(&config.paths.work_dir, config.limits.min_free_work_mib)),
        ),
        Check::from_result(
            "log dir",
            writable_dir(&config.paths.log_dir, run_id)
                .map(|()| config.paths.log_dir.display().to_string()),
        ),
    ];

    match runtime.server_version().await {
        Ok(version) => {
            checks.push(Check::ok("container runtime", format!("server {version}")));
            checks.extend(image_checks(config, &runtime, run_id).await);
        }
        Err(error) => checks.push(Check::fail("container runtime", error.to_string())),
    }

    let unpinned = config.unpinned_images();
    if !unpinned.is_empty() {
        checks.push(Check::warn(
            "image pinning",
            format!("not pinned by digest: {}", unpinned.join(", ")),
        ));
    }

    print!("{}", format_checks(&checks));
    Ok(if checks.iter().any(|c| c.status == Status::Fail) {
        Outcome::Failed
    } else {
        Outcome::Clean
    })
}

async fn image_checks(config: &Config, runtime: &Runtime, run_id: &str) -> Vec<Check> {
    let mut checks = Vec::new();

    match runtime
        .ensure_image(&config.ocr.image, config.containers.pull)
        .await
    {
        Err(error) => checks.push(Check::fail("ocr image", error.to_string())),
        Ok(()) => checks.push(tesseract_languages(config, runtime).await),
    }

    if let Err(error) = runtime
        .ensure_image(&config.dzi.image, config.containers.pull)
        .await
    {
        checks.push(Check::fail("dzi image", error.to_string()));
        return checks;
    }
    let version = runtime
        .run(&tool_spec(&config.dzi.image, "vips", &["--version"]))
        .await;
    checks.push(Check::from_result(
        "dzi image",
        version.map(|done| done.stdout_text().trim().to_owned()),
    ));
    checks.push(webtree_access(config, runtime, run_id).await);
    checks
}

fn tool_spec(image: &str, entrypoint: &str, args: &[&str]) -> Spec {
    Spec {
        image: image.to_owned(),
        user: None,
        mounts: Vec::new(),
        entrypoint: Some(entrypoint.to_owned()),
        args: args.iter().map(Into::into).collect(),
        timeout: PROBE_TIMEOUT,
    }
}

async fn tesseract_languages(config: &Config, runtime: &Runtime) -> Check {
    const NAME: &str = "tesseract languages";
    let listed = runtime
        .run(&tool_spec(
            &config.ocr.image,
            "tesseract",
            &["--list-langs"],
        ))
        .await;
    let done = match listed {
        Ok(done) => done,
        Err(error) => return Check::fail(NAME, error.to_string()),
    };
    // Tesseract prints a heading line, then one language per line.
    let output = format!(
        "{}{}",
        done.stdout_text(),
        String::from_utf8_lossy(&done.stderr)
    );
    let available: Vec<&str> = output.lines().skip(1).map(str::trim).collect();
    let missing: Vec<&str> = config
        .ocr
        .languages
        .values()
        .map(String::as_str)
        .filter(|lang| !available.contains(lang))
        .collect();
    if missing.is_empty() {
        Check::ok(NAME, available.join(", "))
    } else {
        Check::fail(
            NAME,
            format!(
                "{} lacks {}; it has {}",
                config.ocr.image,
                missing.join(", "),
                available.join(", ")
            ),
        )
    }
}

/// Asset containers run as the webtree owner: it must be able to create the staging directory
/// in the public files and read the source images.
async fn webtree_access(config: &Config, runtime: &Runtime, run_id: &str) -> Check {
    const NAME: &str = "webtree owner access";
    // Values are passed as positional parameters, never spliced into the script.
    let script = r#"mkdir -p "/files/$1" && touch "/files/$1/.probe-$2" && rm "/files/$1/.probe-$2" \
        && test -r /source && test -x /source"#;
    let spec = Spec {
        image: config.dzi.image.clone(),
        user: Some((config.webtree.owner_uid, config.webtree.owner_gid)),
        mounts: vec![
            Mount {
                host: config.paths.public_files.clone(),
                target: "/files".into(),
                read_only: false,
            },
            Mount {
                host: config.paths.source_root.clone(),
                target: "/source".into(),
                read_only: true,
            },
        ],
        entrypoint: Some("sh".into()),
        args: vec![
            "-c".into(),
            script.into(),
            "sh".into(),
            config.webtree.staging_dir.clone().into_os_string(),
            run_id.into(),
        ],
        timeout: PROBE_TIMEOUT,
    };
    Check::from_result(
        NAME,
        runtime.run(&spec).await.map(|_| {
            format!(
                "{}:{} can write {} and read {}",
                config.webtree.owner_uid,
                config.webtree.owner_gid,
                config
                    .paths
                    .public_files
                    .join(&config.webtree.staging_dir)
                    .display(),
                config.paths.source_root.display()
            )
        }),
    )
}

fn public_files(path: &Path) -> Result<String, String> {
    let pages = path.join("serials/pages");
    if pages.is_dir() {
        Ok(path.display().to_string())
    } else {
        Err(format!(
            "{} is not the site's public files directory: it has no serials/pages",
            path.display()
        ))
    }
}

fn readable_dir(path: &Path) -> Result<String, String> {
    fs::read_dir(path)
        .map(|_| path.display().to_string())
        .map_err(|error| format!("cannot read {}: {error}", path.display()))
}

fn writable_dir(path: &Path, run_id: &str) -> Result<(), String> {
    let describe = |error: std::io::Error| format!("{}: {error}", path.display());
    fs::create_dir_all(path).map_err(describe)?;
    let probe: PathBuf = path.join(format!(".probe-{run_id}"));
    fs::write(&probe, b"").map_err(describe)?;
    match fs::remove_file(&probe) {
        Err(error) if error.kind() != ErrorKind::NotFound => Err(describe(error)),
        _ => Ok(()),
    }
}

fn free_space(path: &Path, min_mib: u64) -> Result<String, String> {
    let stats =
        rustix::fs::statvfs(path).map_err(|error| format!("{}: {error}", path.display()))?;
    let free_mib = stats.f_bavail.saturating_mul(stats.f_frsize) / (1024 * 1024);
    let detail = format!("{}, {free_mib} MiB free", path.display());
    if free_mib >= min_mib {
        Ok(detail)
    } else {
        Err(format!("{detail}; limits.min_free_work_mib is {min_mib}"))
    }
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn writable_dir_creates_and_leaves_nothing_behind() {
        let dir = tempfile::tempdir().unwrap();
        let work = dir.path().join("a/b");
        writable_dir(&work, "run").unwrap();
        assert_eq!(fs::read_dir(&work).unwrap().count(), 0);
    }

    #[test]
    fn free_space_compares_with_the_minimum() {
        let dir = tempfile::tempdir().unwrap();
        assert!(free_space(dir.path(), 0).is_ok());
        assert!(free_space(dir.path(), u64::MAX / (1024 * 1024)).is_err());
    }

    #[test]
    fn public_files_must_look_like_the_site() {
        let dir = tempfile::tempdir().unwrap();
        assert!(public_files(dir.path()).is_err());
        fs::create_dir_all(dir.path().join("serials/pages")).unwrap();
        assert!(public_files(dir.path()).is_ok());
    }
}
