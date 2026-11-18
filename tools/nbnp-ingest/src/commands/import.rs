//! `import <path>`: decides, for every issue under a path, what an import does, prints it, and
//! with `--yes` does it. `--dry-run` only prints.
//!
//! A run creates the issues the site lacks and the pages an issue lacks, installs missing tiles
//! and PDFs, and writes each issue's state file once the site confirms it complete.
//!
//! An issue is created when the site has no issue for it, resumed when the site has part of
//! it, linked when it was imported by the PHP importer (a state file will record the site's
//! UUID), and left alone when it is complete. It is blocked, and nothing is done to it, whenever
//! the site's contents cannot be explained by the source.

mod execution;
mod output;
mod planning;

use std::path::{Path, PathBuf};
use std::time::{Duration, Instant};

use time::OffsetDateTime;
use tracing::info;
use uuid::Uuid;

use crate::adapters::drupal::Client;
use crate::adapters::notify::Notifier;
use crate::adapters::run_log::RunLog;
use crate::adapters::source_tree;
use crate::config::{Config, SiteCredentials};
use crate::domain::finding::{Finding, any_errors};
use crate::domain::metadata::IssueMetadata;
use crate::domain::plan::PagePlan;
use crate::error::{Error, Outcome};
use crate::ingest::execute::IssueDone;
use crate::ingest::prepare::{load_titles, prepare};
use crate::report::outln;
use crate::source::Titles;

use execution::execute;
use output::{Summary, print_plans, print_results, record};
use planning::plan_all;

#[derive(Debug, Clone, Copy, Default)]
pub struct Options {
    /// Use this title instead of each issue's `title_id`, for example on a test site.
    pub title: Option<u32>,
    /// Import an issue again whose state file says complete but which is gone from the site.
    pub force: bool,
    /// List every issue, not only those with something to do.
    pub all: bool,
    pub mode: Mode,
    /// Send the summary to SNS after a run, when `[notify]` is configured.
    pub notify: bool,
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
    /// What carrying out the plan needs; absent for blocked issues.
    info: Option<IssueInfo>,
    /// What the run did, when it ran.
    done: Option<Result<IssueDone, String>>,
    /// How long the run spent on the issue.
    elapsed: Option<Duration>,
}

/// What a run needs, besides the plan, to carry out an issue.
#[derive(Debug, Clone)]
struct IssueInfo {
    dir: PathBuf,
    title_id: u32,
    metadata: Box<IssueMetadata>,
    /// The site issue's UUID, when it exists.
    site_uuid: Option<String>,
    /// The state file already says complete, so none is written.
    state_complete: bool,
    /// Checksums of the source images, when they were read while planning.
    source_checksums: Vec<String>,
}

/// Plans, and with `Mode::Run` carries out, the import of every issue under `root`. A run
/// records itself in `log`, or in a log of its own when none is given.
pub async fn run(
    config: &Config,
    root: &Path,
    options: Options,
    run_id: &str,
    log: Option<&RunLog>,
) -> Result<Outcome, Error> {
    let started = Instant::now();
    let opened;
    let log = match log {
        Some(log) => Some(log),
        None if options.mode == Mode::Run => {
            opened = open_log(config, "import", root)?;
            Some(&opened)
        }
        None => None,
    };
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
            outln!("\nNothing was changed. Run again with --yes to do this.");
            return Ok(Outcome::Clean);
        }
        Mode::Run => {
            let interrupted = execute(config, &client, &mut plans, run_id).await?;
            print_results(root, &tree.issues, &plans);
            if let Some(log) = log {
                record(log, &tree.issues, &plans);
            }
            let summary = Summary::of(root, &plans, started.elapsed());
            outln!("\n{}", summary.text);
            if options.notify {
                notify(config, &summary).await;
            }
            if interrupted {
                outln!(
                    "Stopped with Ctrl-C; failures above may only be the stop. Run the same \
                     command again to resume: nothing is created twice."
                );
                return Ok(Outcome::Failed);
            }
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

/// Opens the run's log and ledger in `paths.log_dir`, and says where they are.
pub fn open_log(config: &Config, command: &str, root: &Path) -> Result<RunLog, Error> {
    let log =
        RunLog::open(&config.paths.log_dir, command, root).map_err(|source| Error::Source {
            path: config.paths.log_dir.clone(),
            source,
        })?;
    outln!(
        "Logging to {} and {}",
        log.log_path.display(),
        log.ledger_path.display()
    );
    Ok(log)
}

/// Sends the summary and the event line; a failure is logged, never fatal: the import is done.
async fn notify(config: &Config, summary: &Summary) {
    let Some(notify) = &config.notify else {
        info!("no [notify] section in the config; no notification sent");
        return;
    };
    let event = summary.event(OffsetDateTime::now_utc());
    match Notifier::new(notify)
        .await
        .send(&summary.text, &event)
        .await
    {
        Ok(()) => info!("notification sent"),
        Err(error) => tracing::warn!("{error}"),
    }
}
