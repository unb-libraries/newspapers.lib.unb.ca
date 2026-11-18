//! `audit <path>`: compares every issue under a path with the site. Reads only; writes nothing.
//!
//! Each issue is found on the site by the UUID in its state file or, for issues the PHP importer
//! brought in, by its metadata in the title's issue list. Pages are compared by image checksum:
//! the source image is always hashed; the stored image is hashed only when the site has no
//! checksum for it yet, or for every page with `--deep`.

use std::collections::{BTreeMap, HashMap};
use std::fmt::Write as _;
use std::fs;
use std::path::{Path, PathBuf};
use std::sync::Arc;

use tokio::sync::Semaphore;
use tokio::task::JoinSet;
use tracing::info;

use crate::adapters::checksum::sha256_file;
use crate::adapters::drupal::{Client, SiteError};
use crate::adapters::source_tree::{self, ImportMarker, IssueDir};
use crate::config::{Config, SiteCredentials};
use crate::domain::audit::{self, LegacyMatch, LocalPage, StoredChecksums};
use crate::domain::finding::{Finding, any_errors};
use crate::domain::metadata::IssueMetadata;
use crate::domain::pages;
use crate::domain::site::{Issue, IssueSummary};
use crate::domain::state::{IssueState, Status};
use crate::error::{Error, Outcome};
use crate::report;
use crate::source::{self, Titles};

#[derive(Debug, Clone, Copy, Default)]
pub struct Options {
    /// Use this title instead of each issue's `title_id`, for example on a test site.
    pub title: Option<u32>,
    /// Also hash every stored image, to find damaged files.
    pub deep: bool,
    /// List every issue, not only those with findings.
    pub all: bool,
}

/// How an issue is found on the site.
#[derive(Debug, Clone)]
enum Lookup {
    /// From this tool's state file.
    Uuid { uuid: String, status: Status },
    /// Imported by the PHP importer, or not imported: search the title's issues.
    Metadata,
}

/// An issue ready to compare with the site.
struct Ready {
    index: usize,
    dir: PathBuf,
    metadata: IssueMetadata,
    title_id: u32,
    pages: Vec<pages::PlannedPage>,
    lookup: Lookup,
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

    let title_lists = load_titles(&client, &mut ready, &mut results).await?;
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

/// Reads what the audit needs from the source. `Err` holds why the issue cannot be audited.
fn prepare(
    index: usize,
    issue: &IssueDir,
    config: &Config,
    titles: &mut Titles,
    title_override: Option<u32>,
) -> Result<(Ready, Vec<Finding>), Vec<Finding>> {
    let languages: Vec<&str> = config.ocr.languages.keys().map(String::as_str).collect();
    let read = source::read_metadata(issue, &config.source, titles, &languages);
    let mut findings = issue.problems.clone();
    findings.extend(read.findings);
    let (planned, page_findings) = pages::plan(config.source.filename_scheme, &issue.pages);
    findings.extend(page_findings);

    let Some(mut metadata) = read.metadata else {
        return Err(findings);
    };
    if let Some(title_id) = title_override {
        metadata.title_id = Some(title_id);
    }
    let Some(title_id) = metadata.title_id else {
        findings.push(Finding::error(
            "no title_id: add a title.toml above the issue, or pass --title",
        ));
        return Err(findings);
    };

    let lookup = if issue.import_marker == ImportMarker::State {
        let path = issue.path.join(&config.source.state_file);
        match fs::read_to_string(&path)
            .map_err(|e| e.to_string())
            .and_then(|text| IssueState::parse(&text).map_err(|e| e.to_string()))
        {
            Ok(state) => Lookup::Uuid {
                uuid: state.issue_uuid,
                status: state.status,
            },
            Err(error) => {
                findings.push(Finding::error(format!(
                    "{}: {error}",
                    config.source.state_file
                )));
                return Err(findings);
            }
        }
    } else {
        Lookup::Metadata
    };

    Ok((
        Ready {
            index,
            dir: issue.path.clone(),
            metadata,
            title_id,
            pages: planned,
            lookup,
        },
        findings,
    ))
}

/// Confirms each title exists, prints its name, and fetches the issue lists that legacy issues
/// are matched against. Issues of a title the site does not have are not audited further.
async fn load_titles(
    client: &Client,
    ready: &mut Vec<Ready>,
    results: &mut [IssueResult],
) -> Result<HashMap<u32, Arc<Vec<IssueSummary>>>, SiteError> {
    let mut needed: BTreeMap<u32, bool> = BTreeMap::new();
    for issue in ready.iter() {
        *needed.entry(issue.title_id).or_default() |= matches!(issue.lookup, Lookup::Metadata);
    }
    let mut lists = HashMap::new();
    let mut missing = Vec::new();
    for (&title_id, &needs_list) in &needed {
        let Some(title) = client.title(title_id).await? else {
            missing.push(title_id);
            continue;
        };
        println!(
            "Title {title_id}: {}",
            title.name.as_deref().unwrap_or(&title.label)
        );
        if needs_list {
            let list = client.title_issues(title_id).await?.unwrap_or_default();
            lists.insert(title_id, Arc::new(list));
        }
    }
    ready.retain(|issue| {
        let absent = missing.contains(&issue.title_id);
        if absent {
            results[issue.index].findings.push(Finding::error(format!(
                "the site has no digital title {}",
                issue.title_id
            )));
        }
        !absent
    });
    Ok(lists)
}

async fn compare_all(
    config: &Config,
    client: &Client,
    ready: Vec<Ready>,
    title_lists: &HashMap<u32, Arc<Vec<IssueSummary>>>,
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
    let found = match find_on_site(client, &issue, title_issues, &mut outcome.findings).await {
        Ok(found) => found,
        Err(error) => {
            outcome.failure = Some(error.to_string());
            return outcome;
        }
    };
    let Some((site, how)) = found else {
        return outcome;
    };
    outcome.site_issue = Some((site.id, how));

    let mut local = Vec::with_capacity(issue.pages.len());
    for page in &issue.pages {
        match sha256_file(issue.dir.join(&page.path)).await {
            Ok(sha256) => local.push(LocalPage {
                path: page.path.clone(),
                page_no: page.page_no.clone(),
                page_sort: page.page_sort.clone(),
                sha256,
            }),
            Err(error) => outcome
                .findings
                .push(Finding::error(format!("{}: {error}", page.path))),
        }
    }

    let mut stored = StoredChecksums::new();
    for page in site
        .pages
        .iter()
        .filter(|p| deep || p.image_sha256.is_none())
    {
        let checksum = match &page.image_path {
            Some(relative) => sha256_file(public_files.join(relative))
                .await
                .map_err(|error| error.to_string()),
            None => Err("the site has no image file for it".to_owned()),
        };
        stored.insert(page.id, checksum);
    }
    outcome
        .findings
        .extend(audit::compare(&issue.metadata, &local, &site, &stored));
    outcome
}

/// The site issue to compare with, and how it was found. `Ok(None)` after adding a finding when
/// there is none to compare with.
async fn find_on_site(
    client: &Client,
    issue: &Ready,
    title_issues: Option<&Vec<IssueSummary>>,
    findings: &mut Vec<Finding>,
) -> Result<Option<(Issue, FoundBy)>, SiteError> {
    match &issue.lookup {
        Lookup::Uuid { uuid, status } => {
            if *status == Status::Pending {
                findings.push(Finding::error(
                    "the last import of this issue did not finish (state file: pending)",
                ));
            }
            let site = client.issue_by_uuid(uuid).await?;
            if site.is_none() {
                findings.push(Finding::error(format!(
                    "not on the site: no issue has UUID {uuid} from the state file"
                )));
            }
            Ok(site.map(|site| (site, FoundBy::Uuid)))
        }
        Lookup::Metadata => {
            let candidates = title_issues.map_or(&[][..], Vec::as_slice);
            match audit::match_legacy(&issue.metadata, issue.pages.len(), candidates) {
                LegacyMatch::None => {
                    findings.push(Finding::error(
                        "not on the site: no issue of the title has this date, volume and issue",
                    ));
                    Ok(None)
                }
                LegacyMatch::Many(ids) => {
                    findings.push(Finding::error(format!(
                        "{} issues on the site match: {}",
                        ids.len(),
                        ids_text(&ids)
                    )));
                    Ok(None)
                }
                LegacyMatch::One {
                    id,
                    empty_duplicates,
                } => {
                    if !empty_duplicates.is_empty() {
                        findings.push(Finding::warning(format!(
                            "empty issues with the same metadata on the site: {}",
                            ids_text(&empty_duplicates)
                        )));
                    }
                    let site = client.issue_by_id(id).await?;
                    if site.is_none() {
                        findings.push(Finding::error(format!(
                            "issue #{id} disappeared from the site during the audit"
                        )));
                    }
                    Ok(site.map(|site| (site, FoundBy::Metadata)))
                }
            }
        }
    }
}

fn ids_text(ids: &[u64]) -> String {
    ids.iter()
        .map(|id| format!("#{id}"))
        .collect::<Vec<_>>()
        .join(", ")
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
    print!("{out}");
    println!(
        "\nAudited {} under {}: {clean} match the site, {with_errors} differ, \
         {failed} could not be audited.",
        report::counted(issues.len(), "issue"),
        root.display()
    );
    if by_metadata > 0 {
        println!(
            "  Found by metadata (imported before this tool): {}.",
            report::counted(by_metadata, "issue")
        );
    }
}
