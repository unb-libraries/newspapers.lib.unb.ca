//! Runs external programs from an argument vector, never through a shell.

use std::ffi::{OsStr, OsString};
use std::fmt;
use std::process::Stdio;
use std::time::Duration;

use tokio::process::Command;

/// Lines of stderr kept in a failure message.
const STDERR_TAIL_LINES: usize = 20;

/// A program and its arguments, with a time limit.
#[derive(Debug, Clone)]
pub struct Invocation {
    program: OsString,
    args: Vec<OsString>,
    timeout: Duration,
}

/// Output of a program that exited with status 0.
#[derive(Debug)]
pub struct Completed {
    pub stdout: Vec<u8>,
    pub stderr: Vec<u8>,
}

impl Completed {
    #[must_use]
    pub fn stdout_text(&self) -> String {
        String::from_utf8_lossy(&self.stdout).into_owned()
    }
}

#[derive(Debug, thiserror::Error)]
pub enum ProcessError {
    #[error("cannot start {command}: {source}")]
    Spawn {
        command: String,
        source: std::io::Error,
    },
    #[error("{command} did not finish within {after:?}")]
    TimedOut { command: String, after: Duration },
    #[error("{command} failed ({status}):\n{stderr}")]
    Failed {
        command: String,
        status: String,
        stderr: String,
    },
}

impl Invocation {
    pub fn new(program: impl Into<OsString>, timeout: Duration) -> Self {
        Self {
            program: program.into(),
            args: Vec::new(),
            timeout,
        }
    }

    #[must_use]
    pub fn arg(mut self, arg: impl Into<OsString>) -> Self {
        self.args.push(arg.into());
        self
    }

    #[must_use]
    pub fn args<I, S>(mut self, args: I) -> Self
    where
        I: IntoIterator<Item = S>,
        S: Into<OsString>,
    {
        self.args.extend(args.into_iter().map(Into::into));
        self
    }

    pub fn argv(&self) -> impl Iterator<Item = &OsStr> {
        std::iter::once(self.program.as_os_str()).chain(self.args.iter().map(OsString::as_os_str))
    }

    /// Runs the program to completion. A non-zero exit status is an error.
    ///
    /// The program is killed if it outlives its timeout or this future is dropped.
    pub async fn run(&self) -> Result<Completed, ProcessError> {
        let child = Command::new(&self.program)
            .args(&self.args)
            .stdin(Stdio::null())
            .stdout(Stdio::piped())
            .stderr(Stdio::piped())
            .kill_on_drop(true)
            .spawn()
            .map_err(|source| ProcessError::Spawn {
                command: self.to_string(),
                source,
            })?;
        let output = match tokio::time::timeout(self.timeout, child.wait_with_output()).await {
            Err(_) => {
                return Err(ProcessError::TimedOut {
                    command: self.to_string(),
                    after: self.timeout,
                });
            }
            Ok(Err(source)) => {
                return Err(ProcessError::Spawn {
                    command: self.to_string(),
                    source,
                });
            }
            Ok(Ok(output)) => output,
        };
        if !output.status.success() {
            return Err(ProcessError::Failed {
                command: self.to_string(),
                status: output.status.to_string(),
                stderr: tail(&output.stderr, STDERR_TAIL_LINES),
            });
        }
        Ok(Completed {
            stdout: output.stdout,
            stderr: output.stderr,
        })
    }
}

/// Shell-quoted, for people reading logs. Never executed.
impl fmt::Display for Invocation {
    fn fmt(&self, f: &mut fmt::Formatter<'_>) -> fmt::Result {
        let mut first = true;
        for word in self.argv() {
            if !first {
                f.write_str(" ")?;
            }
            first = false;
            write!(f, "{}", quote(&word.to_string_lossy()))?;
        }
        Ok(())
    }
}

fn quote(word: &str) -> String {
    let plain = !word.is_empty()
        && word
            .bytes()
            .all(|b| b.is_ascii_alphanumeric() || b"-_./:=@,+%".contains(&b));
    if plain {
        word.to_owned()
    } else {
        format!("'{}'", word.replace('\'', r"'\''"))
    }
}

fn tail(bytes: &[u8], lines: usize) -> String {
    let text = String::from_utf8_lossy(bytes);
    let all: Vec<&str> = text.trim_end().lines().collect();
    all[all.len().saturating_sub(lines)..].join("\n")
}

#[cfg(test)]
mod tests {
    use super::*;

    fn sh(script: &str) -> Invocation {
        Invocation::new("sh", Duration::from_secs(5)).args(["-c", script])
    }

    #[tokio::test]
    async fn captures_output() {
        let done = sh("printf out; printf err >&2").run().await.unwrap();
        assert_eq!(done.stdout_text(), "out");
        assert_eq!(done.stderr, b"err");
    }

    #[tokio::test]
    async fn arguments_are_not_interpreted_by_a_shell() {
        let done = Invocation::new("printf", Duration::from_secs(5))
            .args(["%s", "$(id); rm -rf /"])
            .run()
            .await
            .unwrap();
        assert_eq!(done.stdout_text(), "$(id); rm -rf /");
    }

    #[tokio::test]
    async fn failure_keeps_the_end_of_stderr() {
        let error = sh("for i in $(seq 1 30); do echo line$i >&2; done; exit 3")
            .run()
            .await
            .unwrap_err();
        let ProcessError::Failed { status, stderr, .. } = error else {
            panic!("expected Failed, got {error:?}");
        };
        assert!(status.contains('3'), "{status}");
        assert!(
            stderr.starts_with("line11\n") && stderr.ends_with("line30"),
            "{stderr}"
        );
    }

    #[tokio::test]
    async fn timeout_kills_the_program() {
        let started = std::time::Instant::now();
        let error = Invocation::new("sleep", Duration::from_millis(200))
            .arg("30")
            .run()
            .await
            .unwrap_err();
        assert!(matches!(error, ProcessError::TimedOut { .. }), "{error:?}");
        assert!(started.elapsed() < Duration::from_secs(5));
    }

    #[tokio::test]
    async fn missing_program_is_a_spawn_error() {
        let error = Invocation::new("/nonexistent/program", Duration::from_secs(1))
            .run()
            .await
            .unwrap_err();
        assert!(matches!(error, ProcessError::Spawn { .. }), "{error:?}");
    }

    #[test]
    fn display_quotes_only_what_needs_it() {
        let invocation = Invocation::new("docker", Duration::from_secs(1)).args([
            "run",
            "--label",
            "a=b",
            "it's here",
            "",
        ]);
        assert_eq!(
            invocation.to_string(),
            r"docker run --label a=b 'it'\''s here' ''"
        );
    }
}
