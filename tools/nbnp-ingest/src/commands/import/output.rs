//! What an import prints.

use std::collections::BTreeSet;
use std::fmt::Write as _;
use std::path::Path;
use std::time::Duration;

use serde::Serialize;
use time::OffsetDateTime;

use crate::adapters::run_log::{RunLog, timestamp};
use crate::adapters::source_tree::IssueDir;
use crate::report::{self, out, outln};

use super::{Action, IssuePlan, Mode, Options};

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

pub(super) fn print_plans(root: &Path, issues: &[IssueDir], plans: &[IssuePlan], options: Options) {
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
    out!("{out}");
    outln!(
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

pub(super) fn print_results(root: &Path, issues: &[IssueDir], plans: &[IssuePlan]) {
    let mut out = String::new();
    let (mut ok, mut failed) = (0, 0);
    for (issue, plan) in issues.iter().zip(plans) {
        let Some(done) = &plan.done else {
            continue;
        };
        let (status, text) = match done {
            Ok(done) => {
                ok += 1;
                ("done", done.describe())
            }
            Err(text) => {
                failed += 1;
                ("FAILED", text.clone())
            }
        };
        let _ = writeln!(
            out,
            "{}  {status}: {text}",
            report::shown_path(root, &issue.path).display()
        );
    }
    outln!("\nResults:\n{out}Completed {ok}, failed or skipped {failed}.");
}

/// Failures listed in a summary; the rest are in the ledger.
const SUMMARY_FAILURES: usize = 10;

/// What a run did, for people and for the notification topics.
#[derive(Debug)]
pub(super) struct Summary {
    pub text: String,
    title_ids: BTreeSet<u32>,
    years: BTreeSet<i32>,
    failed: usize,
}

impl Summary {
    pub(super) fn of(root: &Path, plans: &[IssuePlan], elapsed: Duration) -> Self {
        let mut counts = Counts::default();
        let mut failures = Vec::new();
        let mut title_ids = BTreeSet::new();
        let mut years = BTreeSet::new();
        for plan in plans {
            let outcome = outcome(plan);
            counts.add(outcome);
            if let Some(info) = &plan.info
                && plan.done.is_some()
            {
                title_ids.insert(info.title_id);
                years.insert(info.metadata.date.year());
            }
            if let Some(Ok(done)) = &plan.done {
                counts.pages_uploaded += done.pages_uploaded;
            }
            if let (Some(Err(reason)), Some(info)) = (&plan.done, &plan.info) {
                failures.push(format!("{} - {}", info.dir.display(), first_line(reason)));
            }
        }
        let attempted = counts.created + counts.updated + counts.failed;
        let average = elapsed / u32::try_from(attempted.max(1)).unwrap_or(1);
        let mut text = format!(
            "[{}] Import finished\n\
             Run time: {}\n\
             Issues: {} ({attempted} attempted)\n\
             Created: {}\n\
             Added to: {}\n\
             Nothing to do: {}\n\
             Blocked: {}\n\
             Failed: {}\n\
             Pages uploaded: {}\n\
             Average per attempted issue: {}",
            root.display(),
            clock(elapsed),
            plans.len(),
            counts.created,
            counts.updated,
            counts.complete,
            counts.blocked,
            counts.failed,
            counts.pages_uploaded,
            clock(average),
        );
        if !failures.is_empty() {
            text.push_str("\nFailures:");
            for failure in failures.iter().take(SUMMARY_FAILURES) {
                text.push('\n');
                text.push_str(failure);
            }
            if failures.len() > SUMMARY_FAILURES {
                let _ = write!(
                    text,
                    "\n...and {} more (see the ledger)",
                    failures.len() - SUMMARY_FAILURES
                );
            }
        }
        Self {
            text,
            title_ids,
            years,
            failed: counts.failed,
        }
    }

    /// The one-line event the PHP importer sent: `[title:years] import (result)`.
    pub(super) fn event(&self, now: OffsetDateTime) -> String {
        let titles = if self.title_ids.is_empty() {
            "none".to_owned()
        } else {
            self.title_ids
                .iter()
                .map(ToString::to_string)
                .collect::<Vec<_>>()
                .join(",")
        };
        let years = match (self.years.first(), self.years.last()) {
            (Some(first), Some(last)) if first == last => first.to_string(),
            (Some(first), Some(last)) => format!("{first}-{last}"),
            _ => "none".to_owned(),
        };
        let result = match self.failed {
            0 => "complete".to_owned(),
            n => format!("completed with {}", report::counted(n, "failure")),
        };
        format!(
            "newspapers.lib.unb.ca - {}\n[{titles}:{years}] import ({result})",
            timestamp(now)
        )
    }
}

#[derive(Debug, Default)]
struct Counts {
    created: usize,
    updated: usize,
    complete: usize,
    blocked: usize,
    failed: usize,
    pages_uploaded: usize,
}

impl Counts {
    fn add(&mut self, outcome: &str) {
        match outcome {
            "created" => self.created += 1,
            "updated" => self.updated += 1,
            "complete" => self.complete += 1,
            "blocked" => self.blocked += 1,
            _ => self.failed += 1,
        }
    }
}

/// What happened to an issue, as the ledger and the summary name it.
fn outcome(plan: &IssuePlan) -> &'static str {
    match (&plan.done, &plan.action) {
        (Some(Ok(done)), _) if done.created => "created",
        (Some(Ok(_)), _) => "updated",
        (Some(Err(_)), _) => "failed",
        (None, Some(Action::Blocked(_))) => "blocked",
        (None, Some(_)) => "complete",
        (None, None) => "not planned",
    }
}

/// One ledger line.
#[derive(Debug, Serialize)]
struct IssueRecord<'a> {
    time: String,
    path: String,
    outcome: &'static str,
    #[serde(skip_serializing_if = "Option::is_none")]
    site_issue_id: Option<u64>,
    pages_uploaded: usize,
    asset_pages: usize,
    state_written: bool,
    #[serde(skip_serializing_if = "Option::is_none")]
    seconds: Option<f64>,
    #[serde(skip_serializing_if = "Option::is_none")]
    message: Option<&'a str>,
}

/// Writes one ledger line per issue.
pub(super) fn record(log: &RunLog, issues: &[IssueDir], plans: &[IssuePlan]) {
    let time = timestamp(OffsetDateTime::now_utc());
    for (issue, plan) in issues.iter().zip(plans) {
        let done = plan.done.as_ref().and_then(|d| d.as_ref().ok());
        let message = match (&plan.done, &plan.action, &plan.failure) {
            (Some(Err(reason)), _, _) => Some(reason.as_str()),
            (_, _, Some(failure)) => Some(failure.as_str()),
            (None, Some(Action::Blocked(reason)), _) => Some(reason.as_str()),
            _ => None,
        };
        let site_issue_id = done.map(|d| d.issue_id).or(match plan.action {
            Some(Action::Update { site_id, .. }) => Some(site_id),
            _ => None,
        });
        log.record(&IssueRecord {
            time: time.clone(),
            path: issue.path.display().to_string(),
            outcome: outcome(plan),
            site_issue_id,
            pages_uploaded: done.map_or(0, |d| d.pages_uploaded),
            asset_pages: done.map_or(0, |d| d.asset_pages),
            state_written: done.is_some_and(|d| d.state_written),
            seconds: plan
                .elapsed
                .map(|e| (e.as_secs_f64() * 10.0).round() / 10.0),
            message,
        });
    }
}

fn first_line(text: &str) -> String {
    let line = text.lines().next().unwrap_or("").trim();
    if line.chars().count() > 200 {
        format!("{}...", line.chars().take(197).collect::<String>())
    } else {
        line.to_owned()
    }
}

/// `1:02:03`.
fn clock(elapsed: Duration) -> String {
    let secs = elapsed.as_secs();
    format!("{}:{:02}:{:02}", secs / 3600, secs / 60 % 60, secs % 60)
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn clock_reads_like_the_php_summary() {
        assert_eq!(clock(Duration::from_secs(3723)), "1:02:03");
        assert_eq!(clock(Duration::from_secs(59)), "0:00:59");
    }

    #[test]
    fn events_name_titles_years_and_failures() {
        let summary = Summary {
            text: String::new(),
            title_ids: BTreeSet::from([248]),
            years: BTreeSet::from([1909, 1910]),
            failed: 1,
        };
        let now = OffsetDateTime::from_unix_timestamp(1_795_012_573).unwrap();
        assert_eq!(
            summary.event(now),
            "newspapers.lib.unb.ca - 2026-11-18T14:36:13Z\n[248:1909-1910] import (completed with 1 failure)"
        );
        let clean = Summary {
            failed: 0,
            years: BTreeSet::from([1910]),
            ..summary
        };
        assert!(clean.event(now).ends_with("[248:1910] import (complete)"));
    }

    #[test]
    fn long_failures_are_cut_to_one_short_line() {
        assert_eq!(first_line("a\nb"), "a");
        assert_eq!(first_line(&"x".repeat(300)).chars().count(), 200);
    }
}
