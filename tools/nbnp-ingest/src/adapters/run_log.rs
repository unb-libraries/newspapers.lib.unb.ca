//! The records a run leaves in the log directory: a log of everything it printed and logged,
//! and a ledger with one JSON line per issue it acted on, then a summary line.
//!
//! The log file is opened once a run knows what it is working on; log lines written before
//! then go only to the terminal.

use std::fs::{self, File, OpenOptions};
use std::io::{self, Write};
use std::path::{Path, PathBuf};
use std::sync::{Mutex, OnceLock};

use serde::Serialize;
use time::OffsetDateTime;

/// The log file of this process's run, once opened; log lines are copied to it.
static LOG_FILE: OnceLock<Mutex<File>> = OnceLock::new();

#[derive(Debug)]
pub struct RunLog {
    pub log_path: PathBuf,
    pub ledger_path: PathBuf,
    ledger: Mutex<File>,
}

impl RunLog {
    /// Opens `<log_dir>/<UTC time>_<command>_<name of root>.log` and `.jsonl`.
    pub fn open(log_dir: &Path, command: &str, root: &Path) -> io::Result<Self> {
        fs::create_dir_all(log_dir)?;
        let name = root
            .file_name()
            .map_or_else(|| "root".to_owned(), |n| n.to_string_lossy().into_owned());
        let stem = format!("{}_{command}_{name}", file_time(OffsetDateTime::now_utc()));
        let log_path = log_dir.join(format!("{stem}.log"));
        let ledger_path = log_dir.join(format!("{stem}.jsonl"));
        let open = |path: &Path| OpenOptions::new().create(true).append(true).open(path);
        let log = open(&log_path)?;
        let ledger = open(&ledger_path)?;
        // A second run in the same process keeps logging to the first file.
        let _ = LOG_FILE.set(Mutex::new(log));
        Ok(Self {
            log_path,
            ledger_path,
            ledger: Mutex::new(ledger),
        })
    }

    /// Appends one record to the ledger.
    pub fn record<T: Serialize>(&self, record: &T) {
        let Ok(mut line) = serde_json::to_string(record) else {
            return;
        };
        line.push('\n');
        if let Ok(mut ledger) = self.ledger.lock() {
            let _ = ledger.write_all(line.as_bytes());
        }
    }
}

/// Copies text to the run's log file, if one is open.
pub fn copy_to_log(text: &str) {
    write_log(text.as_bytes());
}

fn write_log(bytes: &[u8]) {
    if let Some(file) = LOG_FILE.get()
        && let Ok(mut file) = file.lock()
    {
        let _ = file.write_all(bytes);
    }
}

/// A `tracing` writer that copies log lines into the run's log file, once there is one.
#[derive(Debug, Clone, Copy, Default)]
pub struct LogFileWriter;

impl Write for LogFileWriter {
    fn write(&mut self, buf: &[u8]) -> io::Result<usize> {
        write_log(buf);
        Ok(buf.len())
    }

    fn flush(&mut self) -> io::Result<()> {
        Ok(())
    }
}

/// `2026-11-18T14:36:13Z`.
#[must_use]
pub fn timestamp(at: OffsetDateTime) -> String {
    format!(
        "{:04}-{:02}-{:02}T{:02}:{:02}:{:02}Z",
        at.year(),
        u8::from(at.month()),
        at.day(),
        at.hour(),
        at.minute(),
        at.second()
    )
}

/// `2026-11-18_143613`, for file names.
fn file_time(at: OffsetDateTime) -> String {
    format!(
        "{:04}-{:02}-{:02}_{:02}{:02}{:02}",
        at.year(),
        u8::from(at.month()),
        at.day(),
        at.hour(),
        at.minute(),
        at.second()
    )
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn times_are_utc_iso_8601() {
        let at = OffsetDateTime::from_unix_timestamp(1_795_012_573).unwrap();
        assert_eq!(timestamp(at), "2026-11-18T14:36:13Z");
        assert_eq!(file_time(at), "2026-11-18_143613");
    }

    #[test]
    fn records_are_json_lines() {
        let dir = tempfile::tempdir().unwrap();
        let log = RunLog::open(
            dir.path(),
            "import",
            Path::new("/mnt/nbnp/The_News/TN_1910"),
        )
        .unwrap();
        assert!(
            log.ledger_path
                .to_string_lossy()
                .ends_with("_import_TN_1910.jsonl")
        );
        log.record(&serde_json::json!({"path": "a", "outcome": "created"}));
        log.record(&serde_json::json!({"path": "b", "outcome": "failed"}));
        let text = fs::read_to_string(&log.ledger_path).unwrap();
        let lines: Vec<serde_json::Value> = text
            .lines()
            .map(|l| serde_json::from_str(l).unwrap())
            .collect();
        assert_eq!(lines.len(), 2);
        assert_eq!(lines[1]["outcome"], "failed");
    }
}
