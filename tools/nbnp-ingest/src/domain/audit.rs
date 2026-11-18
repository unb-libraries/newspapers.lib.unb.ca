//! Compares an issue in the source tree with the issue on the site.
//!
//! Pages are matched by image checksum, not by name or number: the PHP importer numbered repeated
//! page numbers in directory order, which can differ between runs, and the checksum is what proves
//! the site holds the scanned image. Every difference is an error, metadata included: a
//! correction made on the site must be made in the source too.

use std::collections::HashMap;

use super::finding::Finding;
use super::metadata::IssueMetadata;
use super::site::{Issue, IssueSummary, Page};

/// A page in the source, numbered as an import would number it, with its image checksum.
#[derive(Debug, Clone, PartialEq, Eq)]
pub struct LocalPage {
    pub path: String,
    pub page_no: String,
    pub page_sort: String,
    pub sha256: String,
}

/// The checksum of a page's stored image file, read from the webtree because the site has none
/// recorded, or to look for damage (`--deep`). The error says why it could not be read.
pub type StoredChecksums = HashMap<u64, Result<String, String>>;

/// Everything that differs between the source issue and the site issue.
#[must_use]
pub fn compare(
    metadata: &IssueMetadata,
    local: &[LocalPage],
    site: &Issue,
    stored: &StoredChecksums,
) -> Vec<Finding> {
    let mut findings = Vec::new();
    let differences = metadata_differences(metadata, site);
    if !differences.is_empty() {
        findings.push(Finding::error(format!(
            "metadata differs (source, site): {}",
            differences.join("; ")
        )));
    }
    if site.pages.is_empty() {
        findings.push(Finding::error("the issue has no pages on the site"));
        return findings;
    }
    findings.extend(page_findings(local, &site.pages, stored));
    findings
}

fn metadata_differences(metadata: &IssueMetadata, site: &Issue) -> Vec<String> {
    let mut differences = Vec::new();
    let mut differ = |key: &str, source: &str, on_site: &str| {
        if source != on_site {
            differences.push(format!("{key} {source:?}, {on_site:?}"));
        }
    };
    if let Some(title_id) = metadata.title_id {
        differ(
            "title_id",
            &title_id.to_string(),
            &site.title_id.map_or_else(String::new, |id| id.to_string()),
        );
    }
    differ("issue title", metadata.issue_title(), &site.issue_title);
    differ(
        "date",
        &metadata.date.to_string(),
        site.date.as_deref().unwrap_or(""),
    );
    differ("volume", &metadata.volume, &site.volume);
    differ("issue", &metadata.issue, &site.issue);
    differ("edition", &metadata.edition, &site.edition);
    differ(
        "missing_pages",
        &metadata.missing_pages,
        &site.missing_pages,
    );
    differ("errata", &metadata.errata, &site.errata);
    differ("language", &metadata.language, &site.language);
    differ("media", &metadata.media, &site.media);
    differences
}

fn page_findings(local: &[LocalPage], site: &[Page], stored: &StoredChecksums) -> Vec<Finding> {
    let mut findings = Vec::new();
    let mut unverifiable = Vec::new();
    let mut damaged = Vec::new();
    let mut unreadable = Vec::new();

    // Local pages by checksum; a list, since two scans can be identical.
    let mut by_checksum: HashMap<&str, Vec<&LocalPage>> = HashMap::new();
    for page in local {
        by_checksum.entry(&page.sha256).or_default().push(page);
    }

    let mut matched: Vec<(&LocalPage, &Page)> = Vec::new();
    let mut extra = Vec::new();
    for page in site {
        let from_file = stored.get(&page.id);
        match (&page.image_sha256, from_file) {
            (Some(recorded), Some(Ok(actual))) if recorded != actual => {
                damaged.push(describe(page));
            }
            (Some(_), Some(Err(error))) => unreadable.push(format!("{} ({error})", describe(page))),
            _ => {}
        }
        let checksum = match (&page.image_sha256, from_file) {
            (Some(recorded), _) => recorded.as_str(),
            (None, Some(Ok(actual))) => actual.as_str(),
            (None, Some(Err(error))) => {
                unverifiable.push(format!("{} ({error})", describe(page)));
                continue;
            }
            (None, None) => {
                unverifiable.push(format!("{} (no checksum recorded)", describe(page)));
                continue;
            }
        };
        match by_checksum.get_mut(checksum).and_then(Vec::pop) {
            Some(local_page) => matched.push((local_page, page)),
            None => extra.push(describe(page)),
        }
    }
    let mut missing: Vec<&str> = by_checksum
        .values()
        .flatten()
        .map(|p| p.path.as_str())
        .collect();
    missing.sort_unstable();

    list(
        &mut findings,
        &missing.iter().map(ToString::to_string).collect::<Vec<_>>(),
        "not on the site",
    );
    list(&mut findings, &extra, "on the site but not in the source");
    list(
        &mut findings,
        &unverifiable,
        "cannot verify against the source",
    );
    list(
        &mut findings,
        &damaged,
        "stored image does not match its recorded checksum",
    );
    list(&mut findings, &unreadable, "cannot read the stored image");

    let sort_differs: Vec<String> = matched
        .iter()
        .filter(|(l, s)| l.page_sort != s.page_sort)
        .map(|(l, s)| format!("{} ({:?}, {:?})", l.path, l.page_sort, s.page_sort))
        .collect();
    list(
        &mut findings,
        &sort_differs,
        "page_sort differs (source, site)",
    );

    // Repeated page numbers are suffixed in directory order, so compare the sets, not the pairs.
    let mut local_numbers: Vec<&str> = matched.iter().map(|(l, _)| l.page_no.as_str()).collect();
    let mut site_numbers: Vec<&str> = matched.iter().map(|(_, s)| s.page_no.as_str()).collect();
    local_numbers.sort_unstable();
    site_numbers.sort_unstable();
    if local_numbers != site_numbers {
        findings.push(Finding::error(format!(
            "page numbers differ: source {}, site {}",
            local_numbers.join(" "),
            site_numbers.join(" ")
        )));
    }

    let without = |has: fn(&Page) -> bool| -> Vec<String> {
        site.iter().filter(|p| !has(p)).map(describe).collect()
    };
    list(&mut findings, &without(|p| p.has_dzi), "no tiles (DZI)");
    list(&mut findings, &without(|p| p.has_pdf), "no PDF");
    findings
}

/// One error listing every item, when there are any.
fn list(findings: &mut Vec<Finding>, items: &[String], message: &str) {
    if !items.is_empty() {
        findings.push(Finding::error(format!("{message}: {}", items.join(", "))));
    }
}

fn describe(page: &Page) -> String {
    format!("page {} (#{})", page.page_no, page.id)
}

/// How an issue imported by the PHP importer, which recorded no ID, was found on the site.
#[derive(Debug, Clone, PartialEq, Eq)]
pub enum LegacyMatch {
    One {
        id: u64,
        /// Issues with the same metadata and no pages, left by failed imports.
        empty_duplicates: Vec<u64>,
    },
    None,
    Many(Vec<u64>),
}

/// Finds the site issue with this issue's date, volume, number, title and edition. Failing
/// that, the one with the same date, volume and number, so changed metadata is reported as a
/// difference instead of a missing issue.
#[must_use]
pub fn match_legacy(metadata: &IssueMetadata, issues: &[IssueSummary]) -> LegacyMatch {
    let date = metadata.date.to_string();
    let same_number = |s: &&IssueSummary| {
        s.date.as_deref() == Some(date.as_str())
            && s.volume == metadata.volume
            && s.issue == metadata.issue
    };
    let exact: Vec<&IssueSummary> = issues
        .iter()
        .filter(same_number)
        .filter(|s| s.issue_title == metadata.issue_title() && s.edition == metadata.edition)
        .collect();
    let candidates = if exact.is_empty() {
        issues.iter().filter(same_number).collect()
    } else {
        exact
    };

    let (with_pages, empty): (Vec<&IssueSummary>, Vec<&IssueSummary>) =
        candidates.iter().partition(|s| s.page_count > 0);
    match (with_pages.as_slice(), empty.as_slice()) {
        ([], []) => LegacyMatch::None,
        ([one], empty) | ([], empty @ [one]) => LegacyMatch::One {
            id: one.id,
            empty_duplicates: empty
                .iter()
                .map(|s| s.id)
                .filter(|id| *id != one.id)
                .collect(),
        },
        ([], many) | (many, _) => LegacyMatch::Many(many.iter().map(|s| s.id).collect()),
    }
}

#[cfg(test)]
mod tests {
    use super::*;
    use crate::domain::metadata::IssueFields;

    fn metadata() -> IssueMetadata {
        IssueFields::parse(
            "date = 1909-01-29\nvolume = \"33\"\nissue = \"5\"\ntitle = \"The News\"\n\
             language = \"eng\"\nmedia = \"print\"\ntitle_id = 48",
        )
        .unwrap()
        .resolve(None)
        .unwrap()
    }

    fn site_page(id: u64, page_no: &str, sha: Option<&str>) -> Page {
        Page {
            id,
            uuid: format!("u{id}"),
            page_no: page_no.into(),
            page_sort: format!("{:0>4}", page_no.split('_').next().unwrap()),
            image_path: Some(format!("serials/pages/48/7/7-{page_no}.jpg")),
            asset_basename: Some(format!("7-{page_no}")),
            image_sha256: sha.map(Into::into),
            has_dzi: true,
            has_pdf: true,
        }
    }

    fn local_page(path: &str, page_no: &str, sha: &str) -> LocalPage {
        LocalPage {
            path: path.into(),
            page_no: page_no.into(),
            page_sort: format!("{:0>4}", page_no.split('_').next().unwrap()),
            sha256: sha.into(),
        }
    }

    fn site_issue(pages: Vec<Page>) -> Issue {
        Issue {
            id: 7,
            uuid: "u".into(),
            title_id: Some(48),
            issue_title: "The News".into(),
            volume: "33".into(),
            issue: "5".into(),
            edition: String::new(),
            date: Some("1909-01-29".into()),
            missing_pages: String::new(),
            errata: String::new(),
            language: "eng".into(),
            media: "print".into(),
            pages,
        }
    }

    fn messages(findings: &[Finding]) -> Vec<&str> {
        findings.iter().map(|f| f.message.as_str()).collect()
    }

    #[test]
    fn a_complete_issue_has_no_findings() {
        let local = [
            local_page("a_01.jpg", "01", "aa"),
            local_page("a_02.jpg", "02", "bb"),
        ];
        let site = site_issue(vec![
            site_page(1, "01", Some("aa")),
            site_page(2, "02", Some("bb")),
        ]);
        assert!(compare(&metadata(), &local, &site, &StoredChecksums::new()).is_empty());
    }

    #[test]
    fn repeated_numbers_in_another_order_are_not_findings() {
        // Same images; the PHP importer saw the two 03s in the other order.
        let local = [
            local_page("a.jpg", "03", "aa"),
            local_page("b.jpg", "03_0", "bb"),
        ];
        let site = site_issue(vec![
            site_page(1, "03", Some("bb")),
            site_page(2, "03_0", Some("aa")),
        ]);
        assert!(compare(&metadata(), &local, &site, &StoredChecksums::new()).is_empty());
    }

    #[test]
    fn missing_extra_and_changed_pages_are_findings() {
        let local = [
            local_page("a_01.jpg", "01", "aa"),
            local_page("a_02.jpg", "02", "bb"),
            local_page("a_03.jpg", "03", "cc"),
        ];
        let mut no_pdf = site_page(9, "09", Some("zz"));
        no_pdf.has_pdf = false;
        let site = site_issue(vec![
            site_page(1, "01", Some("aa")),
            site_page(2, "02", Some("xx")),
            no_pdf,
        ]);
        let findings = compare(&metadata(), &local, &site, &StoredChecksums::new());
        assert_eq!(
            messages(&findings),
            [
                "not on the site: a_02.jpg, a_03.jpg",
                "on the site but not in the source: page 02 (#2), page 09 (#9)",
                "no PDF: page 09 (#9)",
            ]
        );
    }

    #[test]
    fn checksums_read_from_the_webtree_fill_gaps_and_find_damage() {
        let local = [
            local_page("a_01.jpg", "01", "aa"),
            local_page("a_02.jpg", "02", "bb"),
        ];
        let site = site_issue(vec![
            site_page(1, "01", None),
            site_page(2, "02", Some("bb")),
            site_page(3, "03", None),
            site_page(4, "04", Some("dd")),
        ]);
        let stored = StoredChecksums::from([
            (1, Ok("aa".to_owned())),
            (2, Ok("rotted".to_owned())),
            (3, Err("No such file or directory".to_owned())),
            (4, Err("Permission denied".to_owned())),
        ]);
        let findings = compare(&metadata(), &local, &site, &stored);
        assert_eq!(
            messages(&findings),
            [
                "on the site but not in the source: page 04 (#4)",
                "cannot verify against the source: page 03 (#3) (No such file or directory)",
                "stored image does not match its recorded checksum: page 02 (#2)",
                "cannot read the stored image: page 04 (#4) (Permission denied)",
            ]
        );
    }

    #[test]
    fn metadata_differences_are_one_finding() {
        let mut site = site_issue(vec![site_page(1, "01", Some("aa"))]);
        site.volume = "34".into();
        site.date = Some("1909-01-30".into());
        let findings = compare(
            &metadata(),
            &[local_page("a_01.jpg", "01", "aa")],
            &site,
            &StoredChecksums::new(),
        );
        assert_eq!(
            messages(&findings),
            [
                "metadata differs (source, site): date \"1909-01-29\", \"1909-01-30\"; volume \"33\", \"34\""
            ]
        );
    }

    #[test]
    fn an_empty_site_issue_is_one_finding() {
        let findings = compare(
            &metadata(),
            &[local_page("a_01.jpg", "01", "aa")],
            &site_issue(vec![]),
            &StoredChecksums::new(),
        );
        assert_eq!(messages(&findings), ["the issue has no pages on the site"]);
    }

    fn summary(id: u64, issue_title: &str, issue: &str, page_count: u32) -> IssueSummary {
        IssueSummary {
            id,
            uuid: format!("u{id}"),
            issue_title: issue_title.into(),
            volume: "33".into(),
            issue: issue.into(),
            edition: String::new(),
            date: Some("1909-01-29".into()),
            page_count,
        }
    }

    #[test]
    fn legacy_issues_are_matched_by_metadata() {
        let issues = [
            summary(1, "The News", "5", 4),
            summary(2, "Supplement to The News", "5", 2),
            summary(3, "The News", "6", 4),
        ];
        assert_eq!(
            match_legacy(&metadata(), &issues),
            LegacyMatch::One {
                id: 1,
                empty_duplicates: vec![]
            }
        );
        assert_eq!(match_legacy(&metadata(), &issues[2..]), LegacyMatch::None);
    }

    #[test]
    fn empty_duplicates_are_set_aside_and_real_duplicates_reported() {
        let with_empty = [
            summary(1, "The News", "5", 4),
            summary(4, "The News", "5", 0),
        ];
        assert_eq!(
            match_legacy(&metadata(), &with_empty),
            LegacyMatch::One {
                id: 1,
                empty_duplicates: vec![4]
            }
        );
        let duplicated = [
            summary(1, "The News", "5", 4),
            summary(5, "The News", "5", 4),
        ];
        assert_eq!(
            match_legacy(&metadata(), &duplicated),
            LegacyMatch::Many(vec![1, 5])
        );
    }

    #[test]
    fn a_retitled_issue_is_still_found() {
        let issues = [summary(1, "The News (Moncton)", "5", 4)];
        assert!(matches!(
            match_legacy(&metadata(), &issues),
            LegacyMatch::One { id: 1, .. }
        ));
    }
}
