//! The endpoints and JSON documents shared with the site's `digital_serial_ingest` module
//! (`IngestNormalizer.php`, `TitleIssueList.php`). Change both sides together.
//!
//! The documents are the types in `domain::site`, which name their fields as the site does.
//! Unknown fields are ignored, so the site can add fields before the tool reads them.

use serde::Deserialize;
use serde_json::{Value, json};
use uuid::Uuid;

use crate::domain::metadata::IssueMetadata;

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

/// Where new tiles go, relative to the public files directory. The site looks there among
/// other places (`SerialPage::getDziUri`), and the PHP importer installed there.
#[must_use]
pub fn dzi_dir(title_id: u32, issue_id: u64) -> String {
    format!("{DZI_ROOT}/{title_id}/{issue_id}")
}

/// The directory holding every title's tile directories.
pub const DZI_ROOT: &str = "serials/dzi";

/// Where new page PDFs go, relative to the public files directory (`SerialPage::getPdfUri`).
#[must_use]
pub fn pdf_dir(title_id: u32, issue_id: u64) -> String {
    format!("serials/pdf/{title_id}/{issue_id}")
}

/// `POST` a new issue (core REST).
pub const ISSUE_CREATE: &str = "/entity/digital_serial_issue?_format=json";

/// `POST` a new page (core REST).
pub const PAGE_CREATE: &str = "/entity/digital_serial_page?_format=json";

/// `POST` a page image as the raw request body (core REST file upload).
pub const PAGE_IMAGE_UPLOAD: &str =
    "/file/upload/digital_serial_page/digital_serial_page/page_image?_format=json";

/// A UUID no issue has, for checking that the credentials work: the answer is 404, not 401.
pub const PROBE_UUID: &str = "00000000-0000-4000-8000-000000000000";

/// The new issue: every field the importer sets, with the UUID the tool chose.
#[must_use]
pub fn issue_body(uuid: &Uuid, title_id: u32, metadata: &IssueMetadata) -> Value {
    json!({
        "uuid": [{ "value": uuid.to_string() }],
        "parent_title": [{ "target_id": title_id }],
        "issue_title": [{ "value": metadata.issue_title() }],
        "issue_vol": [{ "value": metadata.volume }],
        "issue_issue": [{ "value": metadata.issue }],
        "issue_edition": [{ "value": metadata.edition }],
        "issue_date": [{ "value": metadata.date.to_string() }],
        "issue_missingp": [{ "value": metadata.missing_pages }],
        "issue_errata": [{ "value": metadata.errata }],
        "issue_language": [{ "value": metadata.language }],
        "issue_media": [{ "value": metadata.media }],
    })
}

/// A new page of an issue, with its derived UUID, uploaded image, OCR text and hOCR.
#[must_use]
pub fn page_body(page: &NewPageFields<'_>) -> Value {
    json!({
        "uuid": [{ "value": page.uuid.to_string() }],
        "parent_issue": [{ "target_id": page.issue_id }],
        "page_no": [{ "value": page.page_no }],
        "page_sort": [{ "value": page.page_sort }],
        "page_image": [{ "target_id": page.file_id }],
        "page_ocr": [{ "value": page.text }],
        "page_hocr": [{ "value": page.hocr }],
    })
}

/// What a new page is created with.
#[derive(Debug, Clone, Copy)]
pub struct NewPageFields<'a> {
    pub uuid: &'a Uuid,
    pub issue_id: u64,
    pub page_no: &'a str,
    pub page_sort: &'a str,
    pub file_id: u64,
    pub text: &'a str,
    pub hocr: &'a str,
}

/// The name an uploaded page image gets, as the PHP importer named it: the issue ID and the page
/// number padded to four digits, keeping a repeat suffix (`7-0003_0.jpg`).
#[must_use]
pub fn upload_file_name(issue_id: u64, page_no: &str) -> String {
    let suffix = page_no.rfind('_').map_or(0, |i| page_no.len() - i);
    format!("{issue_id}-{page_no:0>width$}.jpg", width = 4 + suffix)
}

/// The answer to creating an entity: core REST returns the entity, its ID as `[{"value": n}]`.
#[derive(Debug, Deserialize)]
pub struct Created {
    id: Vec<IntValue>,
}

/// The answer to an upload: the new file entity.
#[derive(Debug, Deserialize)]
pub struct UploadedFile {
    fid: Vec<IntValue>,
}

#[derive(Debug, Deserialize)]
struct IntValue {
    value: u64,
}

impl Created {
    pub fn id(&self) -> Result<u64, super::SiteError> {
        first(&self.id, "id")
    }
}

impl UploadedFile {
    pub fn fid(&self) -> Result<u64, super::SiteError> {
        first(&self.fid, "fid")
    }
}

fn first(values: &[IntValue], name: &str) -> Result<u64, super::SiteError> {
    values
        .first()
        .map(|v| v.value)
        .ok_or_else(|| super::SiteError::Unexpected(format!("the site's answer has no {name}")))
}

/// The body of an error response.
#[derive(Debug, Deserialize)]
pub struct ErrorBody {
    pub error: String,
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn upload_names_follow_the_php_importer() {
        assert_eq!(upload_file_name(53317, "01"), "53317-0001.jpg");
        assert_eq!(upload_file_name(53317, "12"), "53317-0012.jpg");
        assert_eq!(upload_file_name(7, "03_0"), "7-0003_0.jpg");
        assert_eq!(upload_file_name(7, "12345"), "7-12345.jpg");
    }

    #[test]
    fn creates_report_their_new_id() {
        let created: Created =
            serde_json::from_str(r#"{"id":[{"value":7}],"uuid":[{"value":"u"}]}"#).unwrap();
        assert_eq!(created.id().unwrap(), 7);
        let file: UploadedFile = serde_json::from_str(r#"{"fid":[{"value":12}]}"#).unwrap();
        assert_eq!(file.fid().unwrap(), 12);
    }

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
