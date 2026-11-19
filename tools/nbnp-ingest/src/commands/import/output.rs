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

use crate::ingest::prepare::TitleNames;

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
         Create {}, add to {}, {} no-op, {} blocked{}.\n  \
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

/// What a run did, for people and for the notification topics. Plain text, so it reads the
/// same in a terminal, an email and Slack, which turns the bare links into links.
#[derive(Debug)]
pub(super) struct Summary {
    pub text: String,
    /// Titles of the issues the run worked on: ID, name and link.
    titles: Vec<(u32, String, String)>,
    years: BTreeSet<i32>,
    failed: usize,
    /// Issues created, added to or failed: zero when the run changed and tried nothing.
    attempted: usize,
}

/// New or completed issues listed in a summary; the rest are in the ledger.
const SUMMARY_ISSUES: usize = 10;

impl Summary {
    pub(super) fn of(
        root: &Path,
        plans: &[IssuePlan],
        elapsed: Duration,
        base_url: &str,
        title_names: &TitleNames,
    ) -> Self {
        let mut counts = Counts::default();
        let mut failures = Vec::new();
        let mut title_ids = BTreeSet::new();
        let mut years = BTreeSet::new();
        let mut issues = Vec::new();
        for plan in plans {
            counts.add(outcome(plan));
            let Some(info) = &plan.info else {
                continue;
            };
            if plan.done.is_some() {
                title_ids.insert(info.title_id);
                years.insert(info.metadata.date.year());
            }
            match &plan.done {
                Some(Ok(done)) => {
                    counts.pages_uploaded += done.pages_uploaded;
                    let m = &info.metadata;
                    let mut name = format!("vol. {}, no. {}", m.volume, m.issue);
                    if !m.edition.is_empty() {
                        let _ = write!(name, ", ed. {}", m.edition);
                    }
                    if !m.supplement_title.is_empty() {
                        let _ = write!(name, " ({})", m.supplement_title);
                    }
                    issues.push((
                        m.date,
                        format!(
                            "• {} · {name} · {}",
                            m.date,
                            issue_url(base_url, info.title_id, done.issue_id)
                        ),
                    ));
                }
                Some(Err(reason)) => failures.push(format!(
                    "• {}: {}",
                    report::shown_path(root, &info.dir).display(),
                    first_line(reason)
                )),
                None => {}
            }
        }
        issues.sort();
        let titles: Vec<(u32, String, String)> = title_ids
            .iter()
            .map(|&id| {
                let name = title_names
                    .get(&id)
                    .cloned()
                    .unwrap_or_else(|| format!("Title {id}"));
                (id, name, title_url(base_url, id))
            })
            .collect();

        let attempted = counts.created + counts.updated + counts.failed;
        let average = elapsed / u32::try_from(attempted.max(1)).unwrap_or(1);
        let heading = if titles.is_empty() {
            "nothing imported".to_owned()
        } else {
            titles
                .iter()
                .map(|(_, name, _)| name.as_str())
                .collect::<Vec<_>>()
                .join("; ")
        };
        let mut text = format!("Import finished: {heading}{}\n", years_text(&years, ", "));
        for (_, _, url) in &titles {
            let _ = writeln!(text, "{url}");
        }
        let _ = write!(
            text,
            "\nCreated {}, added to {}, {} no-op, {} blocked, {} failed.\n\
             {} uploaded in {} ({} per issue{}).\n\
             From {}\n",
            report::counted(counts.created, "issue"),
            counts.updated,
            counts.complete,
            counts.blocked,
            counts.failed,
            report::counted(counts.pages_uploaded, "page"),
            clock(elapsed),
            clock(average),
            pages_per_hour(counts.pages_uploaded, elapsed)
                .map_or_else(String::new, |rate| format!(", {rate} pages an hour")),
            root.display(),
        );
        let issue_lines: Vec<String> = issues.into_iter().map(|(_, line)| line).collect();
        list_section(&mut text, "Issues", &issue_lines, SUMMARY_ISSUES);
        list_section(&mut text, "Failures", &failures, SUMMARY_FAILURES);
        Self {
            text: text.trim_end().to_owned(),
            titles,
            years,
            failed: counts.failed,
            attempted,
        }
    }

    /// True when the run created, added to and failed nothing: every issue was already
    /// complete or skipped. Such a run sends no notification.
    pub(super) fn is_no_op(&self) -> bool {
        self.attempted == 0
    }

    /// The line the PHP importer sent, `[title:years] import (result)`, then each title's link.
    pub(super) fn event(&self, now: OffsetDateTime) -> String {
        let ids = if self.titles.is_empty() {
            "none".to_owned()
        } else {
            self.titles
                .iter()
                .map(|(id, _, _)| id.to_string())
                .collect::<Vec<_>>()
                .join(",")
        };
        let years = match years_text(&self.years, "") {
            text if text.is_empty() => "none".to_owned(),
            text => text,
        };
        let result = match self.failed {
            0 => "complete".to_owned(),
            n => format!("completed with {}", report::counted(n, "failure")),
        };
        let mut event = format!(
            "newspapers.lib.unb.ca - {}\n[{ids}:{years}] import ({result})",
            timestamp(now)
        );
        for (_, name, url) in &self.titles {
            let _ = write!(event, "\n{name}: {url}");
        }
        event
    }
}

/// `1909` or `1908-1910`, after `prefix`; empty when there are no years.
fn years_text(years: &BTreeSet<i32>, prefix: &str) -> String {
    match (years.first(), years.last()) {
        (Some(first), Some(last)) if first == last => format!("{prefix}{first}"),
        (Some(first), Some(last)) => format!("{prefix}{first}-{last}"),
        _ => String::new(),
    }
}

/// A blank line, the heading, at most `limit` lines, and how many more the ledger has.
fn list_section(text: &mut String, heading: &str, lines: &[String], limit: usize) {
    if lines.is_empty() {
        return;
    }
    let _ = writeln!(text, "\n{heading}:");
    for line in lines.iter().take(limit) {
        let _ = writeln!(text, "{line}");
    }
    if lines.len() > limit {
        let _ = writeln!(text, "…and {} more (see the ledger)", lines.len() - limit);
    }
}

/// The public page listing a title's issues.
fn title_url(base_url: &str, title_id: u32) -> String {
    format!("{base_url}/serials/browse/{title_id}")
}

/// The public page of an issue.
fn issue_url(base_url: &str, title_id: u32, issue_id: u64) -> String {
    format!("{base_url}/serials/{title_id}/issues/{issue_id}")
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

/// Pages uploaded per hour of the run, when there were any and the run took time.
fn pages_per_hour(pages: usize, elapsed: Duration) -> Option<u64> {
    let secs = elapsed.as_secs_f64();
    if pages == 0 || secs < 1.0 {
        return None;
    }
    #[expect(
        clippy::cast_possible_truncation,
        clippy::cast_sign_loss,
        clippy::cast_precision_loss,
        reason = "a page count and a positive rate, both far inside f64 and u64"
    )]
    let rate = (pages as f64 * 3600.0 / secs).round() as u64;
    Some(rate)
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
    fn rate_is_pages_an_hour() {
        assert_eq!(pages_per_hour(464, Duration::from_secs(1159)), Some(1441));
        assert_eq!(pages_per_hour(0, Duration::from_mins(1)), None);
        assert_eq!(pages_per_hour(8, Duration::ZERO), None);
    }

    #[test]
    fn clock_reads_like_the_php_summary() {
        assert_eq!(clock(Duration::from_secs(3723)), "1:02:03");
        assert_eq!(clock(Duration::from_secs(59)), "0:00:59");
    }

    #[test]
    fn events_keep_the_php_line_and_add_title_links() {
        let summary = Summary {
            text: String::new(),
            titles: vec![(
                332,
                "The Kings County Record".into(),
                title_url("https://newspapers.lib.unb.ca", 332),
            )],
            years: BTreeSet::from([1902, 1903]),
            failed: 1,
            attempted: 3,
        };
        let now = OffsetDateTime::from_unix_timestamp(1_795_012_573).unwrap();
        assert_eq!(
            summary.event(now),
            "newspapers.lib.unb.ca - 2026-11-18T14:36:13Z\n\
             [332:1902-1903] import (completed with 1 failure)\n\
             The Kings County Record: https://newspapers.lib.unb.ca/serials/browse/332"
        );
        let clean = Summary {
            failed: 0,
            years: BTreeSet::from([1902]),
            ..summary
        };
        assert!(clean.event(now).contains("[332:1902] import (complete)"));
    }

    #[test]
    fn a_run_that_did_nothing_is_a_no_op() {
        let summary = Summary::of(
            Path::new("/mnt/nbnp/KingsCountyRecord/KCR_1909"),
            &[],
            Duration::from_secs(2),
            "https://newspapers.lib.unb.ca",
            &TitleNames::default(),
        );
        assert!(summary.is_no_op());
        assert!(summary.text.contains("0 no-op"), "{}", summary.text);
        let busy = Summary {
            attempted: 1,
            ..summary
        };
        assert!(!busy.is_no_op());
    }

    #[test]
    fn long_lists_are_cut_with_a_pointer_to_the_ledger() {
        let mut text = String::new();
        list_section(
            &mut text,
            "Issues",
            &(1..=17).map(|n| format!("• {n}")).collect::<Vec<_>>(),
            15,
        );
        assert!(text.starts_with("\nIssues:\n• 1\n"));
        assert!(text.ends_with("• 15\n…and 2 more (see the ledger)\n"));
        let mut none = String::new();
        list_section(&mut none, "Failures", &[], 10);
        assert!(none.is_empty());
    }

    #[test]
    fn long_failures_are_cut_to_one_short_line() {
        assert_eq!(first_line("a\nb"), "a");
        assert_eq!(first_line(&"x".repeat(300)).chars().count(), 200);
    }
}
