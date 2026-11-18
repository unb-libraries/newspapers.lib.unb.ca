//! What an import prints.

use std::fmt::Write as _;
use std::path::Path;

use crate::adapters::source_tree::IssueDir;
use crate::report;

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

pub(super) fn print_results(root: &Path, issues: &[IssueDir], plans: &[IssuePlan]) {
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
