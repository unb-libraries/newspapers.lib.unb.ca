//! What the site holds: issues, their pages and titles, as its ingest endpoints describe them.
//! The field names are the JSON names (see `adapters::drupal::contract`).

use serde::Deserialize;

#[derive(Debug, Clone, PartialEq, Eq, Deserialize)]
#[expect(
    clippy::struct_field_names,
    reason = "the names are the site's JSON field names"
)]
pub struct Issue {
    pub id: u64,
    pub uuid: String,
    pub title_id: Option<u32>,
    pub issue_title: String,
    pub volume: String,
    pub issue: String,
    pub edition: String,
    /// `YYYY-MM-DD`; absent only on broken records.
    pub date: Option<String>,
    pub missing_pages: String,
    pub errata: String,
    pub language: String,
    pub media: String,
    /// Every page, published or not, ordered by `page_sort`, `page_no`, `id`.
    pub pages: Vec<Page>,
}

#[derive(Debug, Clone, PartialEq, Eq, Deserialize)]
#[expect(
    clippy::struct_field_names,
    reason = "the names are the site's JSON field names"
)]
pub struct Page {
    pub id: u64,
    pub uuid: String,
    pub page_no: String,
    pub page_sort: String,
    /// Relative to the site's public files directory; absent when the image file is gone.
    pub image_path: Option<String>,
    /// The stored image's file name without extension, which its tiles and PDF are named after.
    pub asset_basename: Option<String>,
    /// Absent until the site has checksummed the image.
    pub image_sha256: Option<String>,
    pub has_dzi: bool,
    pub has_pdf: bool,
}

#[derive(Debug, Clone, PartialEq, Eq, Deserialize)]
pub struct Title {
    pub id: u32,
    pub uuid: String,
    /// The publication's name, when the title is linked to one.
    pub name: Option<String>,
    pub label: String,
}

#[derive(Debug, Clone, PartialEq, Eq, Deserialize)]
pub struct TitleIssues {
    pub title_id: u32,
    /// Oldest first.
    pub issues: Vec<IssueSummary>,
}

#[derive(Debug, Clone, PartialEq, Eq, Deserialize)]
pub struct IssueSummary {
    pub id: u64,
    pub uuid: String,
    pub issue_title: String,
    pub volume: String,
    pub issue: String,
    pub edition: String,
    pub date: Option<String>,
    /// Every page, published or not.
    pub page_count: u32,
}
