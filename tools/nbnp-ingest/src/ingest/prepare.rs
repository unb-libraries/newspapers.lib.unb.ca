//! Reads an issue directory into what matching it with the site needs, and checks its title.

use std::collections::{BTreeMap, HashMap};
use std::fs;
use std::path::PathBuf;
use std::sync::Arc;

use crate::adapters::drupal::{Client, SiteError};
use crate::adapters::source_tree::{ImportMarker, IssueDir};
use crate::config::Config;
use crate::domain::finding::Finding;
use crate::domain::metadata::IssueMetadata;
use crate::domain::pages::{self, PlannedPage};
use crate::domain::site::IssueSummary;
use crate::domain::state::{IssueState, Status};
use crate::report::outln;
use crate::source::{self, Titles};

/// How an issue is found on the site.
#[derive(Debug, Clone)]
pub enum Lookup {
    /// From this tool's state file.
    Uuid { uuid: String, status: Status },
    /// No state file: imported by the PHP importer, or not at all. Search the title's issues.
    Metadata,
}

/// An issue directory read and ready to be matched with the site.
#[derive(Debug)]
pub struct Ready {
    /// Position in the scanned tree, so results print in tree order.
    pub index: usize,
    pub dir: PathBuf,
    pub metadata: IssueMetadata,
    pub title_id: u32,
    pub pages: Vec<PlannedPage>,
    pub lookup: Lookup,
}

/// Every title's issue summaries, for the titles that have issues without a state file.
pub type TitleLists = HashMap<u32, Arc<Vec<IssueSummary>>>;

/// Each title's name, as the site shows it.
pub type TitleNames = BTreeMap<u32, String>;

/// Reads metadata, pages and state file. `Err` holds why the issue cannot be matched; `Ok` also
/// returns findings that do not prevent it.
pub fn prepare(
    index: usize,
    issue: &IssueDir,
    config: &Config,
    titles: &mut Titles,
    title_override: Option<u32>,
) -> Result<(Ready, Vec<Finding>), Vec<Finding>> {
    let languages: Vec<&str> = config.ocr.languages.keys().map(String::as_str).collect();
    let read = source::read_metadata(issue, &config.source, titles, &languages);
    let mut findings = issue.problems.clone();
    findings.extend(read.findings);
    let (planned, page_findings) = pages::plan(config.source.filename_scheme, &issue.pages);
    findings.extend(page_findings);

    let Some(mut metadata) = read.metadata else {
        return Err(findings);
    };
    if let Some(title_id) = title_override {
        metadata.title_id = Some(title_id);
    }
    let Some(title_id) = metadata.title_id else {
        findings.push(Finding::error(
            "no title_id: add a title.toml above the issue, or pass --title",
        ));
        return Err(findings);
    };

    let lookup = if issue.import_marker == ImportMarker::State {
        let path = issue.path.join(&config.source.state_file);
        match fs::read_to_string(&path)
            .map_err(|e| e.to_string())
            .and_then(|text| IssueState::parse(&text).map_err(|e| e.to_string()))
        {
            Ok(state) => Lookup::Uuid {
                uuid: state.issue_uuid,
                status: state.status,
            },
            // Never treated as absent: that could create the issue a second time.
            Err(error) => {
                findings.push(Finding::error(format!(
                    "{}: {error}",
                    config.source.state_file
                )));
                return Err(findings);
            }
        }
    } else {
        Lookup::Metadata
    };

    Ok((
        Ready {
            index,
            dir: issue.path.clone(),
            metadata,
            title_id,
            pages: planned,
            lookup,
        },
        findings,
    ))
}

/// Confirms each title exists, prints its name, and fetches the issue lists that issues without
/// a state file are matched against. Issues of a title the site does not have are removed from
/// `ready` and returned with the reason.
pub async fn load_titles(
    client: &Client,
    ready: &mut Vec<Ready>,
) -> Result<(TitleLists, TitleNames, Vec<(usize, Finding)>), SiteError> {
    let mut needed: BTreeMap<u32, bool> = BTreeMap::new();
    for issue in ready.iter() {
        *needed.entry(issue.title_id).or_default() |= matches!(issue.lookup, Lookup::Metadata);
    }
    let mut lists = HashMap::new();
    let mut names = TitleNames::new();
    let mut missing = Vec::new();
    for (&title_id, &needs_list) in &needed {
        let Some(title) = client.title(title_id).await? else {
            missing.push(title_id);
            continue;
        };
        let name = title.name.unwrap_or(title.label);
        outln!("Title {title_id}: {name}");
        names.insert(title_id, name);
        if needs_list {
            let list = client.title_issues(title_id).await?.unwrap_or_default();
            lists.insert(title_id, Arc::new(list));
        }
    }
    let mut rejected = Vec::new();
    ready.retain(|issue| {
        let absent = missing.contains(&issue.title_id);
        if absent {
            rejected.push((
                issue.index,
                Finding::error(format!("the site has no digital title {}", issue.title_id)),
            ));
        }
        !absent
    });
    Ok((lists, names, rejected))
}
