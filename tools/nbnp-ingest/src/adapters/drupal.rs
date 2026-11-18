//! The site, newspapers.lib.unb.ca: its read endpoints for this tool, and a client for them.

pub mod client;
pub mod contract;

pub use client::{Client, SiteError};
