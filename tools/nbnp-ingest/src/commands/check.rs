//! `check <path>`: reads every issue under a path as an import would, without contacting the
//! site, and reports what would stop or surprise an import.

use std::fmt::Write as _;
use std::path::Path;

use tracing::info;

use crate::adapters::source_tree::{self, ImportMarker, IssueDir};
use crate::config::Config;
use crate::domain::finding::{Finding, any_errors};
use crate::domain::pages;
use crate::error::{Error, Outcome};
use crate::source::{self, Origin, Titles};

#[derive(Debug, Default)]
struct Totals {
    issues: usize,
    pages: usize,
    skipped: usize,
    with_errors: usize,
    with_warnings: usize,
    issue_files: usize,
    legacy_only: usize,
    imported_by_php: usize,
    without_title_id: usize,
}

pub fn run(config: &Config, root: &Path, verbose: bool) -> Result<Outcome, Error> {
    info!("scanning {}", root.display());
    let tree = source_tree::scan(root, &config.source).map_err(|source| Error::Source {
        path: root.to_owned(),
        source,
    })?;
    if tree.issues.is_empty() && tree.problems.is_empty() {
        eprintln!(
            "No issue directories under {}: none holds {} or {}.",
            root.display(),
            config.source.issue_file,
            config.source.legacy_metadata_file
        );
        return Ok(Outcome::Failed);
    }

    let languages: Vec<&str> = config.ocr.languages.keys().map(String::as_str).collect();
    let mut titles = Titles::default();
    let mut totals = Totals::default();
    let mut out = String::new();

    for issue in &tree.issues {
        let (findings, read) = check_issue(issue, config, &mut titles, &languages);
        totals.issues += 1;
        totals.pages += issue.pages.len();
        totals.skipped += issue.skipped.len();
        totals.imported_by_php += usize::from(issue.import_marker == ImportMarker::Legacy);
        match read.origin {
            Origin::IssueFile => totals.issue_files += 1,
            Origin::LegacyMetadata => totals.legacy_only += 1,
        }
        if read.metadata.as_ref().is_some_and(|m| m.title_id.is_none()) {
            totals.without_title_id += 1;
        }
        if any_errors(&findings) {
            totals.with_errors += 1;
        } else if !findings.is_empty() {
            totals.with_warnings += 1;
        }
        if verbose || !findings.is_empty() {
            write_issue(&mut out, root, issue, &findings);
        }
    }
    for problem in &tree.problems {
        let _ = writeln!(out, "{:<5}  {}", problem.severity, problem.message);
    }

    print!("{out}");
    print_summary(root, &totals, tree.problems.len());
    Ok(if totals.with_errors > 0 || any_errors(&tree.problems) {
        Outcome::Findings
    } else {
        Outcome::Clean
    })
}

fn check_issue(
    issue: &IssueDir,
    config: &Config,
    titles: &mut Titles,
    languages: &[&str],
) -> (Vec<Finding>, source::Read) {
    let mut findings = issue.problems.clone();
    if !issue.writable {
        findings.push(Finding::error(
            "not writable by this user; import and convert write files here",
        ));
    }
    let mut read = source::read_metadata(issue, &config.source, titles, languages);
    findings.append(&mut read.findings);
    findings.extend(pages::plan(&issue.pages).1);
    if let Some(parent) = &issue.parent_issue {
        findings.push(Finding::warning(format!(
            "inside issue directory {}; the PHP importer also added these pages to that issue",
            parent.display()
        )));
    }
    findings.sort_by_key(|f| std::cmp::Reverse(f.severity));
    (findings, read)
}

fn write_issue(out: &mut String, root: &Path, issue: &IssueDir, findings: &[Finding]) {
    let shown = issue.path.strip_prefix(root).unwrap_or(&issue.path);
    let shown = if shown.as_os_str().is_empty() {
        Path::new(".")
    } else {
        shown
    };
    let _ = writeln!(
        out,
        "{}  ({} pages{})",
        shown.display(),
        issue.pages.len(),
        if issue.skipped.is_empty() {
            String::new()
        } else {
            format!(", {} skipped", issue.skipped.len())
        }
    );
    for finding in findings {
        let mut lines = finding.message.lines();
        let _ = writeln!(
            out,
            "  {:<5}  {}",
            finding.severity,
            lines.next().unwrap_or("")
        );
        for line in lines {
            let _ = writeln!(out, "         {line}");
        }
    }
}

fn print_summary(root: &Path, totals: &Totals, tree_problems: usize) {
    println!(
        "\nChecked {} issues ({} pages, {} skipped) under {}.",
        totals.issues,
        totals.pages,
        totals.skipped,
        root.display()
    );
    println!(
        "  {} with errors, {} with warnings only.",
        totals.with_errors, totals.with_warnings
    );
    if tree_problems > 0 {
        println!("  {tree_problems} problems outside issue directories.");
    }
    println!(
        "  Metadata: {} from issue.toml, {} from metadata.php only.",
        totals.issue_files, totals.legacy_only
    );
    if totals.imported_by_php > 0 {
        println!(
            "  {} were imported by the PHP importer.",
            totals.imported_by_php
        );
    }
    if totals.without_title_id > 0 {
        println!(
            "  {} have no title_id from a title.toml; importing them needs --title.",
            totals.without_title_id
        );
    }
}

#[cfg(test)]
mod tests {
    use super::*;
    use crate::domain::finding::Severity;
    use std::fs;

    fn config() -> Config {
        Config::parse(include_str!("../../config.example.toml")).unwrap()
    }

    #[test]
    fn an_issue_with_metadata_and_pages_is_clean() {
        let dir = tempfile::tempdir().unwrap();
        let issue = dir.path().join("TN_1909_33_05");
        fs::create_dir(&issue).unwrap();
        fs::write(
            issue.join("issue.toml"),
            "date = 1909-01-29\nvolume = \"33\"\nissue = \"5\"\ntitle = \"The News\"\n\
             language = \"eng\"\nmedia = \"print\"\n",
        )
        .unwrap();
        fs::write(issue.join("001_TN_1909_33_05_01.jpg"), "x").unwrap();

        let tree = source_tree::scan(dir.path(), &config().source).unwrap();
        let (findings, read) =
            check_issue(&tree.issues[0], &config(), &mut Titles::default(), &["eng"]);
        assert!(findings.is_empty(), "{findings:?}");
        assert!(read.metadata.is_some());
    }

    #[test]
    fn errors_come_before_warnings() {
        let dir = tempfile::tempdir().unwrap();
        let outer = dir.path().join("A");
        let inner = outer.join("A_Sup");
        fs::create_dir_all(&inner).unwrap();
        fs::write(outer.join("issue.toml"), "volume = \"1\"").unwrap();
        fs::write(inner.join("issue.toml"), "volume = \"1\"").unwrap();
        fs::write(inner.join("x_T_1_1_1_01.jpg"), "x").unwrap();

        let tree = source_tree::scan(dir.path(), &config().source).unwrap();
        let (findings, _) =
            check_issue(&tree.issues[1], &config(), &mut Titles::default(), &["eng"]);
        let severities: Vec<_> = findings.iter().map(|f| f.severity).collect();
        assert_eq!(severities, [Severity::Error, Severity::Warning]);
        assert!(findings[1].message.starts_with("inside issue directory"));
    }
}
