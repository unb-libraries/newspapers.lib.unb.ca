//! Deciding what an import does with each issue, from the source and the site.

use std::path::Path;
use std::sync::Arc;

use tokio::sync::Semaphore;
use tokio::task::JoinSet;
use uuid::Uuid;

use crate::adapters::drupal::Client;
use crate::config::Config;
use crate::domain::audit::{LegacyMatch, metadata_differences};
use crate::domain::finding::Finding;
use crate::domain::plan;
use crate::domain::site::IssueSummary;
use crate::domain::state::Status;
use crate::ingest::prepare::{Lookup, Ready, TitleLists};
use crate::ingest::resolve::{Resolution, hash_local, ids_text, resolve, stored_checksums};

use super::{Action, IssueInfo, IssuePlan};

pub(super) async fn plan_all(
    config: &Config,
    client: &Client,
    ready: Vec<Ready>,
    title_lists: &TitleLists,
    force: bool,
    plans: &mut [IssuePlan],
) {
    // Matching an existing issue reads its images; this bounds how many issues read at once.
    let slots = Arc::new(Semaphore::new(config.limits.cpu_slots.max(1)));
    let public_files = Arc::new(config.paths.public_files.clone());
    let mut tasks = JoinSet::new();
    for issue in ready {
        let client = client.clone();
        let slots = Arc::clone(&slots);
        let public_files = Arc::clone(&public_files);
        let list = title_lists.get(&issue.title_id).cloned();
        let ocr_language = config.ocr.languages.get(&issue.metadata.language).cloned();
        tasks.spawn(async move {
            let _slot = slots.acquire_owned().await;
            let index = issue.index;
            let mut plan = plan_issue(&client, issue, list.as_deref(), &public_files, force).await;
            plan.ocr_language = ocr_language;
            (index, plan)
        });
    }
    while let Some(joined) = tasks.join_next().await {
        match joined {
            Ok((index, plan)) => {
                let target = &mut plans[index];
                target.findings.extend(plan.findings);
                target.action = plan.action;
                target.pages = plan.pages;
                target.ocr_language = plan.ocr_language;
                target.failure = plan.failure;
                target.info = plan.info;
            }
            Err(error) => tracing::error!("a planning task failed: {error}"),
        }
    }
}

pub(super) async fn plan_issue(
    client: &Client,
    issue: Ready,
    title_issues: Option<&Vec<IssueSummary>>,
    public_files: &Path,
    force: bool,
) -> IssuePlan {
    let mut result = IssuePlan::default();
    let resolution = match resolve(client, &issue, title_issues.map(Vec::as_slice)).await {
        Ok(resolution) => resolution,
        Err(error) => {
            result.failure = Some(error.to_string());
            return result;
        }
    };

    let action = decide(&resolution, force);
    let state_complete = matches!(
        issue.lookup,
        Lookup::Uuid {
            status: Status::Complete,
            ..
        }
    );
    let info = |site_uuid: Option<String>| IssueInfo {
        dir: issue.dir.clone(),
        title_id: issue.title_id,
        metadata: Box::new(issue.metadata.clone()),
        site_uuid,
        state_complete,
        source_checksums: Vec::new(),
    };
    match (&action, resolution.site()) {
        (Action::Create { uuid, .. }, _) => {
            result.pages = plan::new_issue(uuid, &issue.pages);
            result.info = Some(info(None));
        }
        (Action::Update { .. }, Some(site)) => {
            let (local, unreadable) = hash_local(&issue).await;
            if !unreadable.is_empty() {
                result.findings.extend(unreadable);
                result.action = Some(Action::Blocked("source images cannot be read".into()));
                return result;
            }
            let stored = stored_checksums(site, public_files, false).await;
            result.pages = plan::existing_issue(&local, site, &stored);
            result.info = Some(IssueInfo {
                source_checksums: local.iter().map(|p| p.sha256.clone()).collect(),
                ..info(Some(site.uuid.clone()))
            });
            let differences = metadata_differences(&issue.metadata, site);
            if !differences.is_empty() {
                result.findings.push(Finding::warning(format!(
                    "metadata differs (source, site), and import does not change it: {}",
                    differences.join("; ")
                )));
            }
        }
        _ => {}
    }
    result.action = Some(if result.pages.blockers.is_empty() {
        action
    } else {
        Action::Blocked(result.pages.blockers.join("; "))
    });
    result
}

/// What to do, given what the site has. Never creates an issue that may already exist.
pub(super) fn decide(resolution: &Resolution, force: bool) -> Action {
    match resolution {
        Resolution::ByUuid {
            site: Some(site), ..
        } => Action::Update {
            site_id: site.id,
            link: false,
        },
        Resolution::ByUuid {
            uuid,
            status,
            site: None,
        } => match (status, Uuid::parse_str(uuid)) {
            (_, Err(_)) => Action::Blocked(format!("the state file's UUID {uuid:?} is invalid")),
            // The create may never have reached the site; creating with the same UUID is safe,
            // as the site refuses a second issue with it.
            (Status::Pending, Ok(uuid)) => Action::Create {
                uuid,
                from_state: true,
            },
            (Status::Complete, Ok(uuid)) if force => Action::Create {
                uuid,
                from_state: true,
            },
            (Status::Complete, Ok(_)) => Action::Blocked(
                "the state file says complete, but no issue on the site has its UUID; it may \
                 have been deleted on purpose. Use --force to import it again"
                    .into(),
            ),
        },
        Resolution::ByMetadata { matched, site } => match (matched, site) {
            (LegacyMatch::None, _) => Action::Create {
                uuid: Uuid::new_v4(),
                from_state: false,
            },
            (LegacyMatch::Many(ids), _) => Action::Blocked(format!(
                "{} issues on the site match; remove the duplicates first: {}",
                ids.len(),
                ids_text(ids)
            )),
            (LegacyMatch::One { .. }, Some(site)) => Action::Update {
                site_id: site.id,
                link: true,
            },
            (LegacyMatch::One { id, .. }, None) => Action::Blocked(format!(
                "issue #{id} disappeared from the site while planning"
            )),
        },
    }
}

#[cfg(test)]
mod tests {
    use super::*;

    const UUID: &str = "6f1c2a4e-1b7d-4c3e-9a51-2d3f4b5c6d7e";

    fn by_uuid(status: Status) -> Resolution {
        Resolution::ByUuid {
            uuid: UUID.into(),
            status,
            site: None,
        }
    }

    #[test]
    fn a_pending_issue_missing_from_the_site_is_created_with_its_uuid() {
        assert_eq!(
            decide(&by_uuid(Status::Pending), false),
            Action::Create {
                uuid: Uuid::parse_str(UUID).unwrap(),
                from_state: true
            }
        );
    }

    #[test]
    fn a_complete_issue_missing_from_the_site_needs_force() {
        assert!(matches!(
            decide(&by_uuid(Status::Complete), false),
            Action::Blocked(_)
        ));
        assert!(matches!(
            decide(&by_uuid(Status::Complete), true),
            Action::Create { .. }
        ));
    }

    #[test]
    fn duplicates_on_the_site_block_and_no_match_creates() {
        let many = Resolution::ByMetadata {
            matched: LegacyMatch::Many(vec![1, 2]),
            site: None,
        };
        assert!(matches!(decide(&many, true), Action::Blocked(_)));
        let none = Resolution::ByMetadata {
            matched: LegacyMatch::None,
            site: None,
        };
        assert!(matches!(decide(&none, false), Action::Create { .. }));
    }
}
