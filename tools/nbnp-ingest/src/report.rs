//! What commands print for people.

use std::fmt::{self, Write as _};

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

#[cfg(test)]
mod tests {
    use super::*;

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
