//! The state file this tool keeps in each issue directory (`.nbnp_issue.toml`).
//!
//! It records the UUID chosen for the issue before it is created on the site, so a later run
//! finds the issue by UUID instead of guessing. The site stays the authority on what exists.

use serde::{Deserialize, Serialize};

/// The format version this tool writes and reads.
pub const VERSION: u32 = 1;

#[derive(Debug, Clone, PartialEq, Eq, Deserialize, Serialize)]
#[serde(deny_unknown_fields)]
pub struct IssueState {
    pub version: u32,
    /// The UUID the issue was, or is about to be, created with.
    pub issue_uuid: String,
    pub status: Status,
    /// Known once the issue exists on the site.
    #[serde(default, skip_serializing_if = "Option::is_none")]
    pub site_issue_id: Option<u64>,
    pub tool_version: String,
}

#[derive(Debug, Clone, Copy, PartialEq, Eq, Deserialize, Serialize)]
#[serde(rename_all = "lowercase")]
pub enum Status {
    /// Written before the issue is created; the import has not finished.
    Pending,
    /// Every page is on the site with its tiles and PDF.
    Complete,
}

#[derive(Debug, thiserror::Error)]
pub enum StateError {
    #[error("{0}")]
    Parse(#[from] toml::de::Error),
    #[error("version {0}; this tool reads version {VERSION}")]
    Version(u32),
    #[error("issue_uuid {0:?} is not a UUID")]
    Uuid(String),
}

impl IssueState {
    pub fn parse(text: &str) -> Result<Self, StateError> {
        let state: Self = toml::from_str(text)?;
        if state.version != VERSION {
            return Err(StateError::Version(state.version));
        }
        if !is_uuid(&state.issue_uuid) {
            return Err(StateError::Uuid(state.issue_uuid));
        }
        Ok(state)
    }
}

impl IssueState {
    /// The state written before an issue is created, so a later run finds it by this UUID.
    #[must_use]
    pub fn pending(issue_uuid: &str) -> Self {
        Self {
            version: VERSION,
            issue_uuid: issue_uuid.to_owned(),
            status: Status::Pending,
            site_issue_id: None,
            tool_version: env!("CARGO_PKG_VERSION").to_owned(),
        }
    }

    /// The state of an issue that is on the site with every page and its tiles and PDF.
    #[must_use]
    pub fn complete(issue_uuid: &str, site_issue_id: u64) -> Self {
        Self {
            version: VERSION,
            issue_uuid: issue_uuid.to_owned(),
            status: Status::Complete,
            site_issue_id: Some(site_issue_id),
            tool_version: env!("CARGO_PKG_VERSION").to_owned(),
        }
    }

    /// The file's text, with a comment saying what wrote it.
    #[must_use]
    pub fn to_toml(&self) -> String {
        // Serializing these plain fields cannot fail.
        let body = toml::to_string(self).unwrap_or_default();
        format!("# Written by nbnp-ingest. Do not edit; delete it only to import again.\n{body}")
    }
}

/// Lowercase hyphenated form, as the site's routes accept it.
fn is_uuid(text: &str) -> bool {
    text.len() == 36
        && text.char_indices().all(|(i, c)| match i {
            8 | 13 | 18 | 23 => c == '-',
            _ => c.is_ascii_digit() || ('a'..='f').contains(&c),
        })
}

#[cfg(test)]
mod tests {
    use super::*;

    const COMPLETE: &str = r#"
        version = 1
        issue_uuid = "6f1c2a4e-1b7d-4c3e-9a51-2d3f4b5c6d7e"
        status = "complete"
        site_issue_id = 7
        tool_version = "0.1.0"
    "#;

    #[test]
    fn reads_a_state_file() {
        let state = IssueState::parse(COMPLETE).unwrap();
        assert_eq!(state.status, Status::Complete);
        assert_eq!(state.site_issue_id, Some(7));
    }

    #[test]
    fn a_written_state_reads_back() {
        let state = IssueState::complete("6f1c2a4e-1b7d-4c3e-9a51-2d3f4b5c6d7e", 7);
        assert_eq!(IssueState::parse(&state.to_toml()).unwrap(), state);
    }

    #[test]
    fn refuses_what_it_cannot_trust() {
        assert!(matches!(
            IssueState::parse(&COMPLETE.replace("version = 1", "version = 2")),
            Err(StateError::Version(2))
        ));
        assert!(matches!(
            IssueState::parse(&COMPLETE.replace("6f1c", "6F1C")),
            Err(StateError::Uuid(_))
        ));
        assert!(IssueState::parse(&COMPLETE.replace("complete", "done")).is_err());
        assert!(IssueState::parse(&format!("{COMPLETE}\nextra = 1")).is_err());
    }
}
