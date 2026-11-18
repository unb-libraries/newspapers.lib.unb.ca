//! The rules of the ingest, with no I/O: metadata, page names and what to report. Adapters and
//! commands feed these modules what they read and act on what they return.

pub mod finding;
pub mod metadata;
pub mod page_name;
pub mod pages;
