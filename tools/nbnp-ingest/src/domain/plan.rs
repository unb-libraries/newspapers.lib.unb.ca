//! Which pages an import creates for an issue, and which pages need their tiles or PDF.
//!
//! Page UUIDs are derived from the issue UUID and the page's path in the issue directory
//! (UUID version 5), so a page whose create timed out is found by UUID on the next run and never created
//! twice, without recording anything per page.

use std::collections::HashMap;

use uuid::Uuid;

use super::audit::{LocalPage, StoredChecksums};
use super::page_name;
use super::pages::PlannedPage;
use super::site::{Issue, Page};

/// A page to create on the site.
#[derive(Debug, Clone, PartialEq, Eq)]
pub struct NewPage {
    pub path: String,
    pub page_no: String,
    pub page_sort: String,
    pub uuid: Uuid,
}

/// A page on the site that is missing generated files.
#[derive(Debug, Clone, PartialEq, Eq)]
pub struct AssetNeed {
    pub page_id: u64,
    pub page_no: String,
    pub dzi: bool,
    pub pdf: bool,
}

#[derive(Debug, Clone, Default, PartialEq, Eq)]
pub struct PagePlan {
    pub create: Vec<NewPage>,
    /// Pages already on the site with the source image.
    pub present: usize,
    pub assets: Vec<AssetNeed>,
    /// Why importing this issue now would be unsafe; nothing is done while there are any.
    pub blockers: Vec<String>,
}

impl PagePlan {
    /// True when the issue needs nothing.
    #[must_use]
    pub fn is_complete(&self) -> bool {
        self.create.is_empty() && self.assets.is_empty() && self.blockers.is_empty()
    }
}

/// The UUID a page of an issue gets, always the same for the same issue and path.
#[must_use]
pub fn page_uuid(issue_uuid: &Uuid, path: &str) -> Uuid {
    Uuid::new_v5(issue_uuid, path.as_bytes())
}

/// Every page of an issue that is not on the site yet.
#[must_use]
pub fn new_issue(issue_uuid: &Uuid, pages: &[PlannedPage]) -> PagePlan {
    PagePlan {
        create: pages
            .iter()
            .map(|page| NewPage {
                path: page.path.clone(),
                page_no: page.page_no.clone(),
                page_sort: page.page_sort.clone(),
                uuid: page_uuid(issue_uuid, &page.path),
            })
            .collect(),
        ..PagePlan::default()
    }
}

/// What an issue already on the site still needs. A source page is on the site when a page has
/// its derived UUID or, for pages created by the PHP importer, the same image checksum.
#[must_use]
pub fn existing_issue(local: &[LocalPage], site: &Issue, stored: &StoredChecksums) -> PagePlan {
    let mut plan = PagePlan::default();
    let Ok(issue_uuid) = Uuid::parse_str(&site.uuid) else {
        plan.blockers.push(format!(
            "the site issue's UUID {:?} is not a UUID",
            site.uuid
        ));
        return plan;
    };

    let mut unmatched_site: Vec<&Page> = site.pages.iter().collect();
    let mut matched: Vec<&Page> = Vec::new();
    let mut missing: Vec<&LocalPage> = Vec::new();

    // By UUID: pages this tool created.
    for page in local {
        let uuid = page_uuid(&issue_uuid, &page.path).to_string();
        match unmatched_site.iter().position(|s| s.uuid == uuid) {
            Some(i) => {
                let site_page = unmatched_site.swap_remove(i);
                if checksum(site_page, stored).is_some_and(|c| c != page.sha256) {
                    plan.blockers.push(format!(
                        "{}: the site page with its UUID (#{}) has a different image",
                        page.path, site_page.id
                    ));
                }
                matched.push(site_page);
            }
            None => missing.push(page),
        }
    }

    // By checksum: pages the PHP importer created. A list per checksum, as scans can be equal.
    let mut by_checksum: HashMap<&str, Vec<&Page>> = HashMap::new();
    let mut unverifiable = Vec::new();
    for page in unmatched_site {
        match checksum(page, stored) {
            Some(sum) => by_checksum.entry(sum).or_default().push(page),
            None => unverifiable.push(format!("page {} (#{})", page.page_no, page.id)),
        }
    }
    let mut to_create: Vec<&LocalPage> = Vec::new();
    for page in missing {
        match by_checksum.get_mut(page.sha256.as_str()).and_then(Vec::pop) {
            Some(site_page) => matched.push(site_page),
            None => to_create.push(page),
        }
    }
    let mut extra: Vec<String> = by_checksum
        .values()
        .flatten()
        .map(|p| format!("page {} (#{})", p.page_no, p.id))
        .collect();
    extra.sort_unstable();
    if !unverifiable.is_empty() {
        plan.blockers.push(format!(
            "cannot tell whether these site pages are in the source: {}",
            unverifiable.join(", ")
        ));
    }
    if !extra.is_empty() {
        plan.blockers.push(format!(
            "on the site but not in the source; delete them on the site or restore their images: {}",
            extra.join(", ")
        ));
    }

    // New pages are numbered after the numbers already on the site.
    let taken: Vec<String> = site.pages.iter().map(|p| p.page_no.clone()).collect();
    let numbers = page_name::distinct_page_numbers(
        to_create
            .iter()
            .map(|p| p.page_no.split('_').next().unwrap_or_default()),
        &taken,
    );
    plan.create = to_create
        .iter()
        .zip(numbers)
        .map(|(page, page_no)| NewPage {
            path: page.path.clone(),
            page_sort: page.page_sort.clone(),
            uuid: page_uuid(&issue_uuid, &page.path),
            page_no,
        })
        .collect();

    plan.present = matched.len();
    plan.assets = matched
        .iter()
        .filter(|p| !p.has_dzi || !p.has_pdf)
        .map(|p| AssetNeed {
            page_id: p.id,
            page_no: p.page_no.clone(),
            dzi: !p.has_dzi,
            pdf: !p.has_pdf,
        })
        .collect();
    plan.assets.sort_by_key(|a| a.page_id);
    plan
}

/// The page image's checksum: recorded by the site, or read from the webtree.
fn checksum<'a>(page: &'a Page, stored: &'a StoredChecksums) -> Option<&'a str> {
    page.image_sha256
        .as_deref()
        .or_else(|| stored.get(&page.id).and_then(|r| r.as_deref().ok()))
}

#[cfg(test)]
mod tests {
    use super::*;

    const ISSUE_UUID: &str = "6f1c2a4e-1b7d-4c3e-9a51-2d3f4b5c6d7e";

    fn issue_uuid() -> Uuid {
        Uuid::parse_str(ISSUE_UUID).unwrap()
    }

    fn local(path: &str, page_no: &str, sha: &str) -> LocalPage {
        LocalPage {
            path: path.into(),
            page_no: page_no.into(),
            page_sort: format!("{:0>4}", page_no.split('_').next().unwrap()),
            sha256: sha.into(),
        }
    }

    fn site_page(id: u64, uuid: &str, page_no: &str, sha: Option<&str>) -> Page {
        Page {
            id,
            uuid: uuid.into(),
            page_no: page_no.into(),
            page_sort: format!("{page_no:0>4}"),
            image_path: Some(format!("serials/pages/1/7/7-{page_no}.jpg")),
            asset_basename: Some(format!("7-{page_no}")),
            image_sha256: sha.map(Into::into),
            has_dzi: true,
            has_pdf: true,
        }
    }

    fn site(pages: Vec<Page>) -> Issue {
        Issue {
            id: 7,
            uuid: ISSUE_UUID.into(),
            title_id: Some(1),
            issue_title: "The News".into(),
            volume: "1".into(),
            issue: "1".into(),
            edition: String::new(),
            date: Some("1909-01-29".into()),
            missing_pages: String::new(),
            errata: String::new(),
            language: "eng".into(),
            media: "print".into(),
            pages,
        }
    }

    fn uuid_of(path: &str) -> String {
        page_uuid(&issue_uuid(), path).to_string()
    }

    #[test]
    fn page_uuids_are_stable_and_distinct() {
        assert_eq!(uuid_of("a_01.jpg"), uuid_of("a_01.jpg"));
        assert_ne!(uuid_of("a_01.jpg"), uuid_of("a_02.jpg"));
    }

    #[test]
    fn a_new_issue_creates_every_page() {
        let pages = [PlannedPage {
            path: "a_01.jpg".into(),
            page_no: "01".into(),
            page_sort: "0001".into(),
        }];
        let plan = new_issue(&issue_uuid(), &pages);
        assert_eq!(plan.create.len(), 1);
        assert_eq!(plan.create[0].uuid.to_string(), uuid_of("a_01.jpg"));
    }

    #[test]
    fn a_resumed_issue_creates_only_what_is_missing() {
        // Page 01 was created by this tool, page 02 by the PHP importer, page 03 is missing.
        let source = [
            local("a_01.jpg", "01", "aa"),
            local("a_02.jpg", "02", "bb"),
            local("a_03.jpg", "03", "cc"),
        ];
        let mut no_pdf = site_page(2, "php-random", "02", Some("bb"));
        no_pdf.has_pdf = false;
        let issue = site(vec![
            site_page(1, &uuid_of("a_01.jpg"), "01", Some("aa")),
            no_pdf,
        ]);
        let plan = existing_issue(&source, &issue, &StoredChecksums::new());
        assert!(plan.blockers.is_empty(), "{:?}", plan.blockers);
        assert_eq!(plan.present, 2);
        assert_eq!(plan.create.len(), 1);
        assert_eq!(plan.create[0].path, "a_03.jpg");
        assert_eq!(plan.create[0].page_no, "03");
        assert_eq!(
            plan.assets,
            [AssetNeed {
                page_id: 2,
                page_no: "02".into(),
                dzi: false,
                pdf: true
            }]
        );
    }

    #[test]
    fn a_complete_issue_needs_nothing() {
        let source = [local("a_01.jpg", "01", "aa")];
        let issue = site(vec![site_page(1, "php-random", "01", None)]);
        let stored = StoredChecksums::from([(1, Ok("aa".to_owned()))]);
        assert!(existing_issue(&source, &issue, &stored).is_complete());
    }

    #[test]
    fn new_pages_do_not_reuse_numbers_on_the_site() {
        // The site has 03 from another image; the new 03 becomes 03_0.
        let source = [
            local("a_03.jpg", "03", "new"),
            local("b_03.jpg", "03", "old"),
        ];
        let issue = site(vec![site_page(1, "php-random", "03", Some("old"))]);
        let plan = existing_issue(&source, &issue, &StoredChecksums::new());
        assert_eq!(plan.create.len(), 1);
        assert_eq!(plan.create[0].page_no, "03_0");
    }

    #[test]
    fn unexplained_site_pages_block_the_issue() {
        let source = [local("a_01.jpg", "01", "aa")];
        let issue = site(vec![
            site_page(1, "x", "01", Some("aa")),
            site_page(2, "y", "02", Some("zz")),
            site_page(3, "z", "03", None),
        ]);
        let plan = existing_issue(&source, &issue, &StoredChecksums::new());
        assert_eq!(plan.blockers.len(), 2, "{:?}", plan.blockers);
        assert!(plan.blockers[0].contains("page 03 (#3)"));
        assert!(plan.blockers[1].contains("page 02 (#2)"));
    }

    #[test]
    fn a_page_with_our_uuid_but_another_image_blocks_the_issue() {
        let source = [local("a_01.jpg", "01", "changed")];
        let issue = site(vec![site_page(1, &uuid_of("a_01.jpg"), "01", Some("aa"))]);
        let plan = existing_issue(&source, &issue, &StoredChecksums::new());
        assert_eq!(plan.blockers.len(), 1);
        assert!(plan.blockers[0].contains("different image"));
    }
}
