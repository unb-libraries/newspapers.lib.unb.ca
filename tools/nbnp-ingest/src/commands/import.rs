//! `import <path>`: decides, for every issue under a path, what an import does, prints it, and
//! with `--yes` does it. `--dry-run` only prints.
//!
//! So far a run completes issues already on the site: it OCRs pages for missing PDFs, tiles
//! pages, installs both, and writes the state file once the site confirms the issue complete.
//! Issues needing new pages are skipped until uploading exists.
//!
//! An issue is created when the site has no issue for it, resumed when the site has part of
//! it, linked when it was imported by the PHP importer (a state file will record the site's
//! UUID), and left alone when it is complete. It is blocked, and nothing is done to it, whenever
//! the site's contents cannot be explained by the source.

use std::fmt::Write as _;
use std::path::{Path, PathBuf};
use std::sync::Arc;

use tokio::sync::Semaphore;
use tokio::task::JoinSet;
use tracing::info;
use uuid::Uuid;

use crate::adapters::assets::Installer;
use crate::adapters::container::Runtime;
use crate::adapters::drupal::Client;
use crate::adapters::ocr::OcrCache;
use crate::adapters::source_tree::{self, IssueDir};
use crate::adapters::source_write::SourceWriter;
use crate::config::{Config, SiteCredentials};
use crate::domain::audit::{LegacyMatch, metadata_differences};
use crate::domain::finding::{Finding, any_errors};
use crate::domain::plan::{self, AssetNeed, PagePlan};
use crate::domain::site::IssueSummary;
use crate::domain::state::{IssueState, Status};
use crate::error::{Error, Outcome};
use crate::ingest::execute::{IssueTarget, Workers, complete_assets, forget_ocr};
use crate::ingest::prepare::{Lookup, Ready, TitleLists, load_titles, prepare};
use crate::ingest::resolve::{Resolution, hash_local, ids_text, resolve, stored_checksums};
use crate::report;
use crate::source::Titles;

#[derive(Debug, Clone, Copy, Default)]
pub struct Options {
    /// Use this title instead of each issue's `title_id`, for example on a test site.
    pub title: Option<u32>,
    /// Import an issue again whose state file says complete but which is gone from the site.
    pub force: bool,
    /// List every issue, not only those with something to do.
    pub all: bool,
    pub mode: Mode,
}

/// Whether a run only shows its plan.
#[derive(Debug, Clone, Copy, Default, PartialEq, Eq)]
pub enum Mode {
    /// `--dry-run`: print the plan.
    DryRun,
    /// Neither flag: print the plan and say how to carry it out.
    #[default]
    ShowOnly,
    /// `--yes`: print the plan and carry it out.
    Run,
}

/// What the import does with an issue.
#[derive(Debug, Clone, PartialEq, Eq)]
enum Action {
    /// Create the issue with this UUID, then all its pages. `from_state` when the UUID is the
    /// one a state file recorded before an earlier import stopped.
    Create { uuid: Uuid, from_state: bool },
    /// Add what is missing to an issue already on the site. `link` writes the state file for
    /// an issue the PHP importer created.
    Update { site_id: u64, link: bool },
    /// Do nothing until the reason is resolved.
    Blocked(String),
}

/// The decision for one issue, with what the import would do to its pages.
#[derive(Debug, Default)]
struct IssuePlan {
    action: Option<Action>,
    pages: PagePlan,
    /// The Tesseract language for new pages.
    ocr_language: Option<String>,
    findings: Vec<Finding>,
    /// Set when the site could not be asked.
    failure: Option<String>,
    /// For an issue already on the site: what completing it needs.
    existing: Option<Existing>,
    /// What the run did, when it ran.
    done: Option<Result<String, String>>,
}

/// An issue on the site, and what a run needs to complete it.
#[derive(Debug, Clone)]
struct Existing {
    dir: PathBuf,
    title_id: u32,
    site_uuid: String,
    /// The state file already says complete, so none is written.
    state_complete: bool,
}

pub async fn run(
    config: &Config,
    root: &Path,
    options: Options,
    run_id: &str,
) -> Result<Outcome, Error> {
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

    let mut plans: Vec<IssuePlan> = Vec::with_capacity(tree.issues.len());
    let mut ready = Vec::new();
    let mut titles = Titles::default();
    for (index, issue) in tree.issues.iter().enumerate() {
        let (prepared, findings) = match prepare(index, issue, config, &mut titles, options.title) {
            Ok((prepared, findings)) => (Some(prepared), findings),
            Err(findings) => (None, findings),
        };
        // An issue with errors in its source is not imported, even partly.
        let blocked = prepared.is_none() || any_errors(&findings);
        plans.push(IssuePlan {
            action: blocked.then(|| Action::Blocked("fix the errors in the source first".into())),
            findings,
            ..IssuePlan::default()
        });
        if let (Some(prepared), false) = (prepared, blocked) {
            ready.push(prepared);
        }
    }

    let (title_lists, rejected) = load_titles(&client, &mut ready).await?;
    for (index, finding) in rejected {
        plans[index].action = Some(Action::Blocked(finding.message));
    }
    plan_all(
        config,
        &client,
        ready,
        &title_lists,
        options.force,
        &mut plans,
    )
    .await;

    print_plans(root, &tree.issues, &plans, options);
    match options.mode {
        Mode::DryRun => {}
        Mode::ShowOnly => {
            println!("\nNothing was changed. Run again with --yes to do this.");
            return Ok(Outcome::Clean);
        }
        Mode::Run => {
            execute(config, &client, &mut plans, run_id).await?;
            print_results(root, &tree.issues, &plans);
        }
    }
    Ok(if plans.iter().any(|p| p.failure.is_some()) {
        Outcome::Failed
    } else if plans
        .iter()
        .any(|p| matches!(p.action, Some(Action::Blocked(_))) || matches!(p.done, Some(Err(_))))
    {
        Outcome::Findings
    } else {
        Outcome::Clean
    })
}

async fn plan_all(
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
                target.existing = plan.existing;
            }
            Err(error) => tracing::error!("a planning task failed: {error}"),
        }
    }
}

async fn plan_issue(
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
    match (&action, resolution.site()) {
        (Action::Create { uuid, .. }, _) => {
            result.pages = plan::new_issue(uuid, &issue.pages);
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
            result.existing = Some(Existing {
                dir: issue.dir.clone(),
                title_id: issue.title_id,
                site_uuid: site.uuid.clone(),
                state_complete: matches!(
                    issue.lookup,
                    Lookup::Uuid {
                        status: Status::Complete,
                        ..
                    }
                ),
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
fn decide(resolution: &Resolution, force: bool) -> Action {
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

#[derive(Debug, Default)]
struct Totals {
    create: usize,
    /// On the site, with pages or assets to add.
    resume: usize,
    /// On the site, needing nothing.
    complete: usize,
    /// Of `resume` and `complete`, those found by metadata.
    link: usize,
    blocked: usize,
    failed: usize,
    pages_to_create: usize,
    pages_needing_assets: usize,
}

fn print_plans(root: &Path, issues: &[IssueDir], plans: &[IssuePlan], options: Options) {
    let all = options.all;
    let mut out = String::new();
    let mut totals = Totals::default();
    for (issue, plan) in issues.iter().zip(plans) {
        let line = describe(plan, &mut totals);
        let quiet = matches!(plan.action, Some(Action::Update { link: false, .. }))
            && plan.pages.is_complete()
            && plan.findings.is_empty();
        if all || !quiet {
            let _ = writeln!(
                out,
                "{}  {line}",
                report::shown_path(root, &issue.path).display()
            );
            report::write_findings(&mut out, &plan.findings);
        }
    }
    print!("{out}");
    println!(
        "\n{}, under {}.\n  \
         Create {}, add to {}, nothing to do for {}, {} blocked{}.\n  \
         Of those on the site, {} came from the PHP importer and get a state file.\n  \
         Pages to upload and OCR: {}; existing pages needing tiles or a PDF: {}.",
        if options.mode == Mode::DryRun {
            "Dry run; nothing was changed"
        } else {
            "Plan"
        },
        root.display(),
        report::counted(totals.create, "issue"),
        totals.resume,
        totals.complete,
        totals.blocked,
        if totals.failed > 0 {
            format!(", {} could not be planned", totals.failed)
        } else {
            String::new()
        },
        totals.link,
        totals.pages_to_create,
        totals.pages_needing_assets,
    );
}

/// One line saying what happens to the issue, counted into `totals`.
fn describe(plan: &IssuePlan, totals: &mut Totals) -> String {
    if let Some(failure) = &plan.failure {
        totals.failed += 1;
        return format!("FAILED  could not plan: {failure}");
    }
    let pages = &plan.pages;
    totals.pages_to_create += pages.create.len();
    totals.pages_needing_assets += pages.assets.len();
    let ocr = plan
        .ocr_language
        .as_deref()
        .map_or_else(String::new, |language| format!(", OCR {language}"));
    match &plan.action {
        Some(Action::Create { uuid, from_state }) => {
            totals.create += 1;
            let with = if *from_state {
                format!("with the state file's UUID {uuid}")
            } else {
                "with a new UUID".to_owned()
            };
            format!(
                "create {with}: {}{ocr}",
                report::counted(pages.create.len(), "page")
            )
        }
        Some(Action::Update { site_id, link }) => {
            let how = if *link {
                totals.link += 1;
                "link"
            } else {
                "issue"
            };
            if pages.is_complete() {
                totals.complete += 1;
                format!("{how} #{site_id}: complete")
            } else {
                totals.resume += 1;
                let mut parts = Vec::new();
                if !pages.create.is_empty() {
                    parts.push(format!(
                        "{} to upload{ocr}",
                        report::counted(pages.create.len(), "page")
                    ));
                }
                if !pages.assets.is_empty() {
                    parts.push(format!(
                        "tiles or PDF for {}",
                        report::counted(pages.assets.len(), "page")
                    ));
                }
                format!("{how} #{site_id}: {}", parts.join(", "))
            }
        }
        Some(Action::Blocked(reason)) => {
            totals.blocked += 1;
            format!("BLOCKED  {reason}")
        }
        None => {
            totals.failed += 1;
            "FAILED  not planned".to_owned()
        }
    }
}

/// Completes every issue already on the site that needs no new pages, one issue at a time;
/// the pages of an issue share the CPU slots.
async fn execute(
    config: &Config,
    client: &Client,
    plans: &mut [IssuePlan],
    run_id: &str,
) -> Result<(), Error> {
    let runtime = Runtime::new(&config.containers, run_id);
    for image in [&config.ocr.image, &config.dzi.image] {
        runtime.ensure_image(image, config.containers.pull).await?;
    }
    let workers = Workers {
        runtime: Arc::new(runtime),
        ocr: Arc::new(OcrCache::new(&config.paths.work_dir, &config.ocr, run_id)),
        installer: Arc::new(Installer::new(config, run_id)),
        cpu: Arc::new(Semaphore::new(config.limits.cpu_slots.max(1))),
    };
    let writer = Arc::new(SourceWriter::new(run_id));
    let state_file: Arc<str> = config.source.state_file.as_str().into();

    // Issues run at the same time, `issue_concurrency` at most, and all their pages share the
    // CPU slots, so a slot freed at the end of one issue goes to the next instead of waiting.
    // Waiting pages are served in order, so earlier issues still finish first.
    let issues = Arc::new(Semaphore::new(config.limits.issue_concurrency.max(1)));
    let mut tasks = JoinSet::new();
    for (index, plan) in plans.iter_mut().enumerate() {
        let Some((target, existing)) = runnable(plan) else {
            continue;
        };
        let needs = plan.pages.assets.clone();
        let (client, workers, writer, state_file, issues) = (
            client.clone(),
            workers.clone(),
            Arc::clone(&writer),
            Arc::clone(&state_file),
            Arc::clone(&issues),
        );
        tasks.spawn(async move {
            let Ok(_issue) = issues.acquire_owned().await else {
                return (index, Err("the run is stopping".to_owned()));
            };
            let done = complete_issue(
                &state_file,
                &client,
                &workers,
                &writer,
                &target,
                &existing,
                &needs,
            )
            .await;
            (index, done)
        });
    }
    while let Some(joined) = tasks.join_next().await {
        match joined {
            Ok((index, done)) => plans[index].done = Some(done),
            Err(error) => tracing::error!("an issue task failed: {error}"),
        }
    }
    workers.runtime.remove_run_containers().await;
    Ok(())
}

/// What a run does with an issue: `None` when there is nothing to run, after recording in
/// `plan.done` why an issue that needs work is skipped.
fn runnable(plan: &mut IssuePlan) -> Option<(IssueTarget, Existing)> {
    if matches!(plan.action, Some(Action::Create { .. })) {
        plan.done = Some(Err("skipped: creating issues is not available yet".into()));
        return None;
    }
    let (Some(Action::Update { site_id, link }), Some(existing)) =
        (plan.action.clone(), plan.existing.clone())
    else {
        return None;
    };
    if !plan.pages.create.is_empty() {
        plan.done = Some(Err(
            "skipped: it needs new pages, and uploading pages is not available yet".into(),
        ));
        return None;
    }
    if plan.pages.assets.is_empty() && (existing.state_complete || !link) {
        return None;
    }
    let Some(ocr_language) = plan.ocr_language.clone() else {
        plan.done = Some(Err("no OCR language is configured for it".into()));
        return None;
    };
    let target = IssueTarget {
        dir: existing.dir.clone(),
        title_id: existing.title_id,
        issue_id: site_id,
        ocr_language,
    };
    Some((target, existing))
}

/// Installs an issue's missing assets, confirms with the site, and records it as complete.
async fn complete_issue(
    state_file: &str,
    client: &Client,
    workers: &Workers,
    writer: &SourceWriter,
    target: &IssueTarget,
    existing: &Existing,
    needs: &[AssetNeed],
) -> Result<String, String> {
    let failures = complete_assets(workers, target, needs).await;
    if !failures.is_empty() {
        return Err(failures.join("; "));
    }

    let site = client
        .issue_by_id(target.issue_id)
        .await
        .map_err(|e| e.to_string())?
        .ok_or("the issue disappeared from the site")?;
    let lacking: Vec<String> = site
        .pages
        .iter()
        .filter(|p| !p.has_dzi || !p.has_pdf)
        .map(|p| format!("page {} (#{})", p.page_no, p.id))
        .collect();
    if !lacking.is_empty() {
        return Err(format!(
            "installed, but the site still lacks tiles or a PDF for: {}",
            lacking.join(", ")
        ));
    }
    forget_ocr(workers, target, needs);

    let mut done = vec![format!(
        "tiles or PDF installed for {}",
        report::counted(needs.len(), "page")
    )];
    if !existing.state_complete {
        let state = IssueState::complete(&existing.site_uuid, target.issue_id).to_toml();
        let path = existing.dir.join(state_file);
        let written = if path.exists() {
            writer.replace(&path, &state)
        } else {
            writer.create(&path, &state)
        };
        written.map_err(|e| format!("complete on the site, but {e}"))?;
        done.push("state file written".into());
    }
    Ok(done.join("; "))
}

fn print_results(root: &Path, issues: &[IssueDir], plans: &[IssuePlan]) {
    let mut out = String::new();
    let (mut ok, mut failed) = (0, 0);
    for (issue, plan) in issues.iter().zip(plans) {
        let Some(done) = &plan.done else {
            continue;
        };
        let (status, text) = match done {
            Ok(text) => {
                ok += 1;
                ("done", text)
            }
            Err(text) => {
                failed += 1;
                ("FAILED", text)
            }
        };
        let _ = writeln!(
            out,
            "{}  {status}: {text}",
            report::shown_path(root, &issue.path).display()
        );
    }
    println!("\nResults:\n{out}Completed {ok}, failed or skipped {failed}.");
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
