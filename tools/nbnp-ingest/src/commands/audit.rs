//! `audit <path>`: compares every issue under a path with the site. Reads only; writes nothing.
//!
//! Each issue is found on the site by the UUID in its state file or, for issues the PHP importer
//! brought in, by its metadata in the title's issue list. Pages are compared by image checksum:
//! the source image is always hashed; the stored image is hashed only when the site has no
//! checksum for it yet, or for every page with `--deep`.

use std::fmt::Write as _;
use std::path::Path;
use std::sync::Arc;

use tokio::sync::Semaphore;
use tokio::task::JoinSet;
use tracing::info;

use crate::adapters::drupal::Client;
use crate::adapters::source_tree::{self, IssueDir};
use crate::config::{Config, SiteCredentials};
use crate::domain::audit::{self, LegacyMatch};
use crate::domain::finding::{Finding, any_errors};
use crate::domain::site::IssueSummary;
use crate::domain::state::Status;
use crate::error::{Error, Outcome};
use crate::ingest::prepare::{Ready, TitleLists, load_titles, prepare};
use crate::ingest::resolve::{Resolution, hash_local, ids_text, resolve, stored_checksums};
use crate::report::{self, out, outln};
use crate::source::Titles;

#[derive(Debug, Clone, Copy, Default)]
pub struct Options {
    /// Use this title instead of each issue's `title_id`, for example on a test site.
    pub title: Option<u32>,
    /// Also hash every stored image, to find damaged files.
    pub deep: bool,
    /// List every issue, not only those with findings.
    pub all: bool,
}

/// How an issue was found on the site.
#[derive(Debug, Clone, Copy, PartialEq, Eq)]
enum FoundBy {
    Uuid,
    Metadata,
}

impl FoundBy {
    fn describe(self) -> &'static str {
        match self {
            Self::Uuid => "by UUID",
            Self::Metadata => "by metadata",
        }
    }
}

/// What the audit found for one issue.
#[derive(Debug, Default)]
struct IssueResult {
    findings: Vec<Finding>,
    /// The site issue compared against, and how it was found.
    site_issue: Option<(u64, FoundBy)>,
    /// Set when the site could not be asked; the issue was not audited.
    failure: Option<String>,
}

pub async fn run(config: &Config, root: &Path, options: Options) -> Result<Outcome, Error> {
    info!("scanning {}", root.display());
    let tree = source_tree::scan(root, &config.source).map_err(|source| Error::Source {
        path: root.to_owned(),
        source,
    })?;
    if tree.issues.is_empty() {
        eprintln!("No issue directories under {}.", root.display());
        return Ok(Outcome::Failed);
    }
    let client = Client::new(
        &config.site,
        SiteCredentials::load(&config.site.credentials_file)?,
    )?;
    client.check_access().await?;

    let mut results: Vec<IssueResult> = Vec::with_capacity(tree.issues.len());
    let mut ready = Vec::new();
    let mut titles = Titles::default();
    for (index, issue) in tree.issues.iter().enumerate() {
        match prepare(index, issue, config, &mut titles, options.title) {
            Ok((prepared, findings)) => {
                ready.push(prepared);
                results.push(IssueResult {
                    findings,
                    ..IssueResult::default()
                });
            }
            Err(findings) => results.push(IssueResult {
                findings,
                ..IssueResult::default()
            }),
        }
    }

    let (title_lists, _, rejected) = load_titles(&client, &mut ready).await?;
    for (index, finding) in rejected {
        results[index].findings.push(finding);
    }
    compare_all(
        config,
        &client,
        ready,
        &title_lists,
        options.deep,
        &mut results,
    )
    .await;

    print_report(root, &tree.issues, &results, options.all);
    Ok(if results.iter().any(|r| r.failure.is_some()) {
        Outcome::Failed
    } else if results.iter().any(|r| any_errors(&r.findings)) {
        Outcome::Findings
    } else {
        Outcome::Clean
    })
}

async fn compare_all(
    config: &Config,
    client: &Client,
    ready: Vec<Ready>,
    title_lists: &TitleLists,
    deep: bool,
    results: &mut [IssueResult],
) {
    // Hashing reads every image; this bounds how many issues read at once.
    let slots = Arc::new(Semaphore::new(config.limits.cpu_slots.max(1)));
    let public_files = Arc::new(config.paths.public_files.clone());
    let mut tasks = JoinSet::new();
    for issue in ready {
        let client = client.clone();
        let slots = Arc::clone(&slots);
        let public_files = Arc::clone(&public_files);
        let list = title_lists.get(&issue.title_id).cloned();
        tasks.spawn(async move {
            let _slot = slots.acquire_owned().await;
            let index = issue.index;
            (
                index,
                audit_issue(&client, issue, list.as_deref(), &public_files, deep).await,
            )
        });
    }
    while let Some(joined) = tasks.join_next().await {
        match joined {
            Ok((index, outcome)) => {
                let result = &mut results[index];
                result.findings.extend(outcome.findings);
                result.site_issue = outcome.site_issue;
                result.failure = outcome.failure;
            }
            Err(error) => tracing::error!("an audit task failed: {error}"),
        }
    }
}

async fn audit_issue(
    client: &Client,
    issue: Ready,
    title_issues: Option<&Vec<IssueSummary>>,
    public_files: &Path,
    deep: bool,
) -> IssueResult {
    let mut outcome = IssueResult::default();
    let resolution = match resolve(client, &issue, title_issues.map(Vec::as_slice)).await {
        Ok(resolution) => resolution,
        Err(error) => {
            outcome.failure = Some(error.to_string());
            return outcome;
        }
    };
    outcome.findings = resolution_findings(&resolution);
    let Some(site) = resolution.site() else {
        return outcome;
    };
    let found_by = match resolution {
        Resolution::ByUuid { .. } => FoundBy::Uuid,
        Resolution::ByMetadata { .. } => FoundBy::Metadata,
    };
    outcome.site_issue = Some((site.id, found_by));

    let (local, unreadable) = hash_local(&issue).await;
    outcome.findings.extend(unreadable);
    let stored = stored_checksums(site, public_files, deep).await;
    outcome
        .findings
        .extend(audit::compare(&issue.metadata, &local, site, &stored));
    outcome
}

/// What the audit reports about how, or whether, the issue was found.
fn resolution_findings(resolution: &Resolution) -> Vec<Finding> {
    let mut findings = Vec::new();
    match resolution {
        Resolution::ByUuid { uuid, status, site } => {
            if *status == Status::Pending {
                findings.push(Finding::error(
                    "the last import of this issue did not finish (state file: pending)",
                ));
            }
            if site.is_none() {
                findings.push(Finding::error(format!(
                    "not on the site: no issue has UUID {uuid} from the state file"
                )));
            }
        }
        Resolution::ByMetadata { matched, site } => match matched {
            LegacyMatch::None => findings.push(Finding::error(
                "not on the site: no issue of the title has this date, volume and issue",
            )),
            LegacyMatch::Many(ids) => findings.push(Finding::error(format!(
                "{} issues on the site match: {}",
                ids.len(),
                ids_text(ids)
            ))),
            LegacyMatch::One {
                id,
                empty_duplicates,
            } => {
                if !empty_duplicates.is_empty() {
                    findings.push(Finding::warning(format!(
                        "empty issues with the same metadata on the site: {}",
                        ids_text(empty_duplicates)
                    )));
                }
                if site.is_none() {
                    findings.push(Finding::error(format!(
                        "issue #{id} disappeared from the site during the audit"
                    )));
                }
            }
        },
    }
    findings
}

fn print_report(root: &Path, issues: &[IssueDir], results: &[IssueResult], all: bool) {
    let mut out = String::new();
    let (mut clean, mut with_errors, mut failed, mut by_metadata) = (0, 0, 0, 0);
    for (issue, result) in issues.iter().zip(results) {
        let has_errors = any_errors(&result.findings);
        if result.failure.is_some() {
            failed += 1;
        } else if has_errors {
            with_errors += 1;
        } else {
            clean += 1;
        }
        if result
            .site_issue
            .is_some_and(|(_, how)| how == FoundBy::Metadata)
        {
            by_metadata += 1;
        }
        if !all && result.findings.is_empty() && result.failure.is_none() {
            continue;
        }
        let site = result.site_issue.map_or_else(String::new, |(id, how)| {
            format!("  -> issue #{id} ({})", how.describe())
        });
        let _ = writeln!(
            out,
            "{}{site}",
            report::shown_path(root, &issue.path).display()
        );
        if let Some(failure) = &result.failure {
            let _ = writeln!(out, "  {:<5}  could not audit: {failure}", "FAIL");
        }
        report::write_findings(&mut out, &result.findings);
    }
    out!("{out}");
    outln!(
        "\nAudited {} under {}: {clean} match the site, {with_errors} differ, \
         {failed} could not be audited.",
        report::counted(issues.len(), "issue"),
        root.display()
    );
    if by_metadata > 0 {
        outln!(
            "  Found by metadata (imported before this tool): {}.",
            report::counted(by_metadata, "issue")
        );
    }
}
