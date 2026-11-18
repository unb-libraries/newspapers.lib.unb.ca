//! An issue's pages: which image files become pages, in what order, with what page numbers.

use super::finding::Finding;
use super::page_name::{self, Scheme};

/// A page image found in an issue directory.
#[derive(Debug, Clone, PartialEq, Eq)]
pub struct SourcePage {
    /// Relative to the issue directory, with `/` separators.
    pub path: String,
    /// File size in bytes.
    pub len: u64,
}

/// A page as it will be created on the site.
#[derive(Debug, Clone, PartialEq, Eq)]
pub struct PlannedPage {
    pub path: String,
    pub page_no: String,
    pub page_sort: String,
}

/// Numbers the pages in path order and reports every page that cannot be imported. Pages must
/// already exclude skipped images.
#[must_use]
pub fn plan(scheme: Scheme, pages: &[SourcePage]) -> (Vec<PlannedPage>, Vec<Finding>) {
    let mut findings = Vec::new();
    let mut sorted: Vec<&SourcePage> = pages.iter().collect();
    sorted.sort_by(|a, b| a.path.cmp(&b.path));

    let mut named = Vec::with_capacity(sorted.len());
    for page in sorted {
        if page.len == 0 {
            findings.push(Finding::error(format!("{} is empty (0 bytes)", page.path)));
        }
        match page_name::page_number(scheme, stem(&page.path)) {
            Ok(number) => named.push((page, number)),
            Err(error) => findings.push(Finding::error(format!("{} {error}", page.path))),
        }
    }
    if pages.is_empty() {
        findings.push(Finding::error("no page images"));
    }

    let numbers = page_name::distinct_page_numbers(named.iter().map(|(_, n)| *n), &[]);
    let planned: Vec<PlannedPage> = named
        .iter()
        .zip(numbers)
        .map(|((page, raw), page_no)| PlannedPage {
            path: page.path.clone(),
            page_sort: page_name::page_sort(raw),
            page_no,
        })
        .collect();

    for page in &planned {
        if page_name::too_long(&page.page_no) {
            findings.push(Finding::error(format!(
                "{}: page number {:?} is longer than the site stores",
                page.path, page.page_no
            )));
        }
    }
    let not_numbers: Vec<String> = planned
        .iter()
        .filter(|p| {
            let raw = p.page_no.split('_').next().unwrap_or_default();
            !raw.chars().all(|c| c.is_ascii_digit())
        })
        .map(|p| format!("{} ({:?})", p.path, p.page_no))
        .collect();
    if !not_numbers.is_empty() {
        findings.push(Finding::warning(format!(
            "page numbers that are not numbers: {}",
            not_numbers.join(", ")
        )));
    }
    let repeated: Vec<&str> = planned
        .iter()
        .filter(|p| p.page_no.contains('_'))
        .map(|p| p.path.as_str())
        .collect();
    if !repeated.is_empty() {
        findings.push(Finding::warning(format!(
            "repeated page numbers, numbered with _0, _1, ...: {}",
            repeated.join(", ")
        )));
    }
    (planned, findings)
}

/// The file name without directories or extension.
fn stem(path: &str) -> &str {
    let name = path.rsplit('/').next().unwrap_or(path);
    name.rsplit_once('.').map_or(name, |(stem, _)| stem)
}

#[cfg(test)]
mod tests {
    use super::*;
    use crate::domain::finding::Severity;

    fn page(path: &str, len: u64) -> SourcePage {
        SourcePage {
            path: path.into(),
            len,
        }
    }

    #[test]
    fn pages_are_numbered_in_path_order() {
        let (planned, findings) = plan(
            Scheme::NbnpV2,
            &[
                page("001_TN_1909_33_05_02.jpg", 10),
                page("001_TN_1909_33_05_01.jpg", 10),
                page("002_TN_1909_33_05_01.jpg", 10),
            ],
        );
        let numbers: Vec<_> = planned
            .iter()
            .map(|p| (p.path.as_str(), p.page_no.as_str(), p.page_sort.as_str()))
            .collect();
        assert_eq!(
            numbers,
            [
                ("001_TN_1909_33_05_01.jpg", "01", "0001"),
                ("001_TN_1909_33_05_02.jpg", "02", "0002"),
                ("002_TN_1909_33_05_01.jpg", "01_0", "0001"),
            ]
        );
        assert_eq!(findings.len(), 1);
        assert_eq!(findings[0].severity, Severity::Warning);
    }

    #[test]
    fn unusable_pages_are_errors() {
        let (planned, findings) = plan(
            Scheme::NbnpV2,
            &[
                page("sub/001_TN_1909_33_05_01.jpg", 0),
                page("cover.jpg", 10),
            ],
        );
        assert_eq!(planned.len(), 1);
        let messages: Vec<_> = findings.iter().map(|f| f.message.as_str()).collect();
        assert_eq!(messages.len(), 2, "{messages:?}");
        assert!(messages[0].starts_with("cover.jpg has 1 `_` fields"));
        assert!(messages[1].ends_with("is empty (0 bytes)"));
    }

    #[test]
    fn page_numbers_that_are_not_numbers_are_warned_about() {
        let (planned, findings) = plan(Scheme::NbnpV2, &[page("005_QCG_1898_02_32_05jpg.jpg", 9)]);
        assert_eq!(planned[0].page_no, "05jpg");
        assert_eq!(findings.len(), 1);
        assert!(findings[0].message.contains("\"05jpg\""), "{findings:?}");
    }

    #[test]
    fn an_issue_needs_pages() {
        let (_, findings) = plan(Scheme::NbnpV2, &[]);
        assert_eq!(findings, [Finding::error("no page images")]);
    }
}
