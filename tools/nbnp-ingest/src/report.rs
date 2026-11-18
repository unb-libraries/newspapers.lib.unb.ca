//! What commands print for people.

use std::fmt::{self, Write as _};
use std::path::Path;

use crate::domain::finding::Finding;

/// The result of one environment check.
#[derive(Debug)]
pub struct Check {
    pub name: &'static str,
    pub status: Status,
    pub detail: String,
}

#[derive(Debug, Clone, Copy, PartialEq, Eq)]
pub enum Status {
    Ok,
    Warn,
    Fail,
}

impl Check {
    pub fn ok(name: &'static str, detail: impl Into<String>) -> Self {
        Self {
            name,
            status: Status::Ok,
            detail: detail.into(),
        }
    }

    pub fn warn(name: &'static str, detail: impl Into<String>) -> Self {
        Self {
            name,
            status: Status::Warn,
            detail: detail.into(),
        }
    }

    pub fn fail(name: &'static str, detail: impl Into<String>) -> Self {
        Self {
            name,
            status: Status::Fail,
            detail: detail.into(),
        }
    }

    /// `ok` with the detail on success, `fail` with the error otherwise.
    pub fn from_result<E: fmt::Display>(name: &'static str, result: Result<String, E>) -> Self {
        match result {
            Ok(detail) => Self::ok(name, detail),
            Err(error) => Self::fail(name, error.to_string()),
        }
    }
}

impl fmt::Display for Status {
    fn fmt(&self, f: &mut fmt::Formatter<'_>) -> fmt::Result {
        f.write_str(match self {
            Self::Ok => "ok",
            Self::Warn => "WARN",
            Self::Fail => "FAIL",
        })
    }
}

/// Formats checks as aligned lines; multi-line details are indented under their check.
#[must_use]
pub fn format_checks(checks: &[Check]) -> String {
    let width = checks.iter().map(|c| c.name.len()).max().unwrap_or(0);
    let mut out = String::new();
    for check in checks {
        let mut lines = check.detail.lines();
        // Writing to a String cannot fail.
        let _ = writeln!(
            out,
            "{:<4}  {:<width$}  {}",
            check.status.to_string(),
            check.name,
            lines.next().unwrap_or("")
        );
        for line in lines {
            let _ = writeln!(out, "{:<4}  {:<width$}  {line}", "", "");
        }
    }
    out
}

/// `count` and `noun`, with an `s` unless the count is one: "1 issue", "3 issues".
#[must_use]
pub fn counted(count: usize, noun: &str) -> String {
    if count == 1 {
        format!("1 {noun}")
    } else {
        format!("{count} {noun}s")
    }
}

/// `path` relative to the directory a command was given, or `.` for that directory itself.
#[must_use]
pub fn shown_path<'a>(root: &Path, path: &'a Path) -> &'a Path {
    match path.strip_prefix(root) {
        Ok(relative) if relative.as_os_str().is_empty() => Path::new("."),
        Ok(relative) => relative,
        Err(_) => path,
    }
}

/// Writes findings indented under their issue, multi-line messages aligned.
pub fn write_findings(out: &mut String, findings: &[Finding]) {
    for finding in findings {
        let mut lines = finding.message.lines();
        // Writing to a String cannot fail.
        let _ = writeln!(
            out,
            "  {:<5}  {}",
            finding.severity,
            lines.next().unwrap_or("")
        );
        for line in lines {
            let _ = writeln!(out, "         {line}");
        }
    }
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn counts_agree_with_their_noun() {
        assert_eq!(counted(1, "issue"), "1 issue");
        assert_eq!(counted(0, "issue"), "0 issues");
    }

    #[test]
    fn paths_are_shown_relative_to_the_root() {
        let root = Path::new("/mnt/nbnp/The_News");
        assert_eq!(
            shown_path(root, Path::new("/mnt/nbnp/The_News/TN_1909")),
            Path::new("TN_1909")
        );
        assert_eq!(shown_path(root, root), Path::new("."));
        assert_eq!(
            shown_path(root, Path::new("/elsewhere")),
            Path::new("/elsewhere")
        );
    }

    #[test]
    fn checks_are_aligned() {
        let text = format_checks(&[
            Check::ok("work dir", "/w, 10 GiB free"),
            Check::fail("tesseract languages", "missing fra\nhave eng, osd"),
        ]);
        assert_eq!(
            text,
            "ok    work dir             /w, 10 GiB free\n\
             FAIL  tesseract languages  missing fra\n\
             \x20                          have eng, osd\n"
        );
    }
}
