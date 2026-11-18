//! The endpoints and JSON documents shared with the site's `digital_serial_ingest` module
//! (`IngestNormalizer.php`, `TitleIssueList.php`). Change both sides together.
//!
//! The documents are the types in `domain::site`, which name their fields as the site does.
//! Unknown fields are ignored, so the site can add fields before the tool reads them.

use serde::Deserialize;

pub use crate::domain::site::{Issue, IssueSummary, Title, TitleIssues};

/// `GET` one issue and its pages by the UUID the tool gave it.
#[must_use]
pub fn issue_by_uuid(uuid: &str) -> String {
    format!("/serials-ingest/issues/uuid/{uuid}")
}

/// `GET` one issue and its pages by site ID.
#[must_use]
pub fn issue_by_id(id: u64) -> String {
    format!("/serials-ingest/issues/{id}")
}

/// `GET` a digital title.
#[must_use]
pub fn title(id: u32) -> String {
    format!("/serials-ingest/titles/{id}")
}

/// `GET` every issue of a digital title, as summaries.
#[must_use]
pub fn title_issues(id: u32) -> String {
    format!("/serials-ingest/titles/{id}/issues")
}

/// A UUID no issue has, for checking that the credentials work: the answer is 404, not 401.
pub const PROBE_UUID: &str = "00000000-0000-4000-8000-000000000000";

/// The body of an error response.
#[derive(Debug, Deserialize)]
pub struct ErrorBody {
    pub error: String,
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn reads_an_issue_document_as_the_site_writes_it() {
        // As IngestNormalizer::issue() returns it, with a field the tool does not know.
        let json = r#"{
            "id": 7, "uuid": "6f1c2a4e-1b7d-4c3e-9a51-2d3f4b5c6d7e", "title_id": 48,
            "issue_title": "The News", "volume": "33", "issue": "5", "edition": "",
            "date": "1909-01-29", "missing_pages": "", "errata": "", "language": "eng",
            "media": "print", "future_field": true,
            "pages": [{
                "id": 1, "uuid": "00000000-0000-4000-8000-000000000001", "page_no": "01",
                "page_sort": "0001", "image_path": "serials/pages/48/7/7-0001.jpg",
                "asset_basename": "7-0001", "image_sha256": null,
                "has_dzi": true, "has_pdf": false
            }]
        }"#;
        let issue: Issue = serde_json::from_str(json).unwrap();
        assert_eq!(issue.pages[0].image_sha256, None);
        assert_eq!(issue.pages[0].asset_basename.as_deref(), Some("7-0001"));
        assert_eq!(issue_by_id(issue.id), "/serials-ingest/issues/7");
    }
}
