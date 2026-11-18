//! Finds an issue directory's issue on the site, and reads the checksums matching needs.

use std::path::Path;

use crate::adapters::checksum::sha256_file;
use crate::adapters::drupal::{Client, SiteError};
use crate::domain::audit::{self, LegacyMatch, LocalPage, StoredChecksums};
use crate::domain::finding::Finding;
use crate::domain::site::{Issue, IssueSummary};
use crate::domain::state::Status;

use super::prepare::{Lookup, Ready};

/// What the site has for an issue directory, before any command decides what that means.
#[derive(Debug)]
pub enum Resolution {
    /// The directory has a state file; `site` is the issue with its UUID, if there is one.
    ByUuid {
        uuid: String,
        status: Status,
        site: Option<Issue>,
    },
    /// No state file. `site` is set when exactly one issue matched and it still exists.
    ByMetadata {
        matched: LegacyMatch,
        site: Option<Issue>,
    },
}

impl Resolution {
    /// The site issue, if one was found.
    #[must_use]
    pub fn site(&self) -> Option<&Issue> {
        match self {
            Self::ByUuid { site, .. } | Self::ByMetadata { site, .. } => site.as_ref(),
        }
    }
}

pub async fn resolve(
    client: &Client,
    issue: &Ready,
    title_issues: Option<&[IssueSummary]>,
) -> Result<Resolution, SiteError> {
    match &issue.lookup {
        Lookup::Uuid { uuid, status } => Ok(Resolution::ByUuid {
            uuid: uuid.clone(),
            status: *status,
            site: client.issue_by_uuid(uuid).await?,
        }),
        Lookup::Metadata => {
            let matched = audit::match_legacy(
                &issue.metadata,
                issue.pages.len(),
                title_issues.unwrap_or_default(),
            );
            let site = match &matched {
                LegacyMatch::One { id, .. } => client.issue_by_id(*id).await?,
                LegacyMatch::None | LegacyMatch::Many(_) => None,
            };
            Ok(Resolution::ByMetadata { matched, site })
        }
    }
}

/// Hashes every page image of the issue. Pages that cannot be read are findings and left out.
pub async fn hash_local(issue: &Ready) -> (Vec<LocalPage>, Vec<Finding>) {
    let mut local = Vec::with_capacity(issue.pages.len());
    let mut findings = Vec::new();
    for page in &issue.pages {
        match sha256_file(issue.dir.join(&page.path)).await {
            Ok(sha256) => local.push(LocalPage {
                path: page.path.clone(),
                page_no: page.page_no.clone(),
                page_sort: page.page_sort.clone(),
                sha256,
            }),
            Err(error) => findings.push(Finding::error(format!("{}: {error}", page.path))),
        }
    }
    (local, findings)
}

/// Hashes stored images in the webtree: those the site has no checksum for, or all of them.
pub async fn stored_checksums(site: &Issue, public_files: &Path, all: bool) -> StoredChecksums {
    let mut stored = StoredChecksums::new();
    for page in site
        .pages
        .iter()
        .filter(|p| all || p.image_sha256.is_none())
    {
        let checksum = match &page.image_path {
            Some(relative) => sha256_file(public_files.join(relative))
                .await
                .map_err(|error| error.to_string()),
            None => Err("the site has no image file for it".to_owned()),
        };
        stored.insert(page.id, checksum);
    }
    stored
}

/// `#1, #5`, for messages.
#[must_use]
pub fn ids_text(ids: &[u64]) -> String {
    ids.iter()
        .map(|id| format!("#{id}"))
        .collect::<Vec<_>>()
        .join(", ")
}
