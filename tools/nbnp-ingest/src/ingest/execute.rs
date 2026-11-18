//! Carries out an issue's plan: create the issue if needed, OCR and upload its new pages, install
//! tiles and PDFs, and record the issue complete once the site confirms every page.
//!
//! Every write that could have happened despite an error is checked by UUID before it is
//! reported as failed, so a run never creates an issue or a page twice. A page holds a CPU slot
//! while it is OCR'd or tiled, and an upload slot while it is uploaded and created; slots are
//! handed out in the order pages ask, so a freed slot goes straight to the next page.

use std::collections::HashMap;
use std::fs;
use std::path::{Path, PathBuf};
use std::sync::Arc;

use tokio::sync::{Mutex, OwnedSemaphorePermit, Semaphore};
use tokio::task::JoinSet;
use tracing::{debug, info};
use uuid::Uuid;

use crate::adapters::assets::{Installer, PageAssets};
use crate::adapters::checksum::sha256_file;
use crate::adapters::container::Runtime;
use crate::adapters::drupal::Client;
use crate::adapters::drupal::contract::{self, NewPageFields};
use crate::adapters::ocr::OcrCache;
use crate::adapters::source_write::SourceWriter;
use crate::domain::metadata::IssueMetadata;
use crate::domain::plan::{AssetNeed, NewPage};
use crate::domain::site::Issue;
use crate::domain::state::IssueState;
use crate::report;

/// What every issue of a run shares.
#[derive(Debug, Clone)]
pub struct Workers {
    pub client: Client,
    pub runtime: Arc<Runtime>,
    pub ocr: Arc<OcrCache>,
    pub installer: Arc<Installer>,
    pub writer: Arc<SourceWriter>,
    /// The state file's name in an issue directory.
    pub state_file: Arc<str>,
    /// OCR and tiling processes.
    pub cpu: Arc<Semaphore>,
    /// Page uploads and creates, across all issues.
    pub uploads: Arc<Semaphore>,
    /// Issues are created one at a time: each create re-saves the title's holdings.
    pub creates: Arc<Mutex<()>>,
}

/// The issue on the site, or the one to create.
#[derive(Debug, Clone)]
pub enum OnSite {
    New {
        uuid: Uuid,
        metadata: Box<IssueMetadata>,
    },
    Existing {
        issue_id: u64,
        uuid: Uuid,
    },
}

/// Everything a run does for one issue.
#[derive(Debug, Clone)]
pub struct IssueJob {
    pub dir: PathBuf,
    pub title_id: u32,
    /// Tesseract's name for the issue's language.
    pub ocr_language: String,
    pub on_site: OnSite,
    pub create: Vec<NewPage>,
    /// Pages already on the site that lack tiles or a PDF.
    pub assets: Vec<AssetNeed>,
    /// The state file already says complete.
    pub state_complete: bool,
}

/// Where an issue is, on both sides.
#[derive(Debug, Clone)]
struct IssueTarget {
    dir: PathBuf,
    title_id: u32,
    issue_id: u64,
    ocr_language: String,
}

/// Carries out the job and says what was done, or why it stopped.
pub async fn run_issue(workers: &Workers, job: &IssueJob) -> Result<String, String> {
    let (issue_id, uuid) = match &job.on_site {
        OnSite::Existing { issue_id, uuid } => (*issue_id, *uuid),
        OnSite::New { uuid, metadata } => {
            // Recorded first: if the run stops after the create, the next run finds the issue
            // by this UUID instead of creating it again.
            write_state(workers, &job.dir, &IssueState::pending(&uuid.to_string()))?;
            let id = create_issue(workers, uuid, job.title_id, metadata).await?;
            info!("created issue #{id} for {}", job.dir.display());
            (id, *uuid)
        }
    };

    let created = create_pages(workers, job, issue_id).await?;
    let site = fetch(&workers.client, issue_id).await?;
    let mut needs = job.assets.clone();
    for page in &job.create {
        needs.extend(new_page_assets(page, &created[&page.uuid], &site)?);
    }

    let target = IssueTarget {
        dir: job.dir.clone(),
        title_id: job.title_id,
        issue_id,
        ocr_language: job.ocr_language.clone(),
    };
    let failures = complete_assets(workers, &target, &needs).await;
    if !failures.is_empty() {
        return Err(failures.join("; "));
    }

    let site = fetch(&workers.client, issue_id).await?;
    let lacking: Vec<String> = site
        .pages
        .iter()
        .filter(|p| !p.has_dzi || !p.has_pdf)
        .map(|p| format!("page {} (#{})", p.page_no, p.id))
        .collect();
    if !lacking.is_empty() {
        return Err(format!(
            "the site still lacks tiles or a PDF for: {}",
            lacking.join(", ")
        ));
    }
    for need in needs.iter().filter(|n| n.pdf) {
        workers.ocr.remove(&need.sha256, &job.ocr_language);
    }

    let mut done = Vec::new();
    if matches!(job.on_site, OnSite::New { .. }) {
        done.push(format!("created issue #{issue_id}"));
    }
    if !job.create.is_empty() {
        done.push(format!(
            "uploaded {}",
            report::counted(job.create.len(), "page")
        ));
    }
    if !needs.is_empty() {
        done.push(format!(
            "tiles and PDF for {}",
            report::counted(needs.len(), "page")
        ));
    }
    if !job.state_complete {
        write_state(
            workers,
            &job.dir,
            &IssueState::complete(&uuid.to_string(), issue_id),
        )
        .map_err(|e| format!("complete on the site, but {e}"))?;
        done.push("state file written".into());
    }
    Ok(done.join("; "))
}

/// Checks a page created in this run against the site, and says what assets it needs.
fn new_page_assets(
    page: &NewPage,
    sha256: &str,
    site: &Issue,
) -> Result<Option<AssetNeed>, String> {
    let uuid = page.uuid.to_string();
    let on_site = site
        .pages
        .iter()
        .find(|p| p.uuid == uuid)
        .ok_or_else(|| format!("{}: created, but the site does not list it", page.path))?;
    if on_site
        .image_sha256
        .as_ref()
        .is_some_and(|stored| stored != sha256)
    {
        return Err(format!(
            "{}: the site stored a different image than was uploaded (page #{})",
            page.path, on_site.id
        ));
    }
    Ok((!on_site.has_dzi || !on_site.has_pdf).then(|| AssetNeed {
        page_id: on_site.id,
        page_no: on_site.page_no.clone(),
        path: page.path.clone(),
        sha256: sha256.to_owned(),
        asset_basename: on_site.asset_basename.clone(),
        dzi: !on_site.has_dzi,
        pdf: !on_site.has_pdf,
    }))
}

/// Creates the issue, or finds that an earlier attempt did.
async fn create_issue(
    workers: &Workers,
    uuid: &Uuid,
    title_id: u32,
    metadata: &IssueMetadata,
) -> Result<u64, String> {
    let _one_at_a_time = workers.creates.lock().await;
    let body = contract::issue_body(uuid, title_id, metadata);
    match workers.client.create_issue(&body).await {
        Ok(id) => Ok(id),
        // A time-out or a lost answer can follow a create that happened; the site then has the
        // issue under this UUID, and refuses a second one.
        Err(error) => match workers.client.issue_by_uuid(&uuid.to_string()).await {
            Ok(Some(issue)) => Ok(issue.id),
            Ok(None) => Err(format!("creating the issue failed: {error}")),
            Err(lookup) => Err(format!(
                "creating the issue failed ({error}), and checking for it failed: {lookup}"
            )),
        },
    }
}

/// Creates every new page; returns each page's image checksum by page UUID.
async fn create_pages(
    workers: &Workers,
    job: &IssueJob,
    issue_id: u64,
) -> Result<HashMap<Uuid, String>, String> {
    let mut tasks = JoinSet::new();
    for page in job.create.iter().cloned() {
        let workers = workers.clone();
        let dir = job.dir.clone();
        let language = job.ocr_language.clone();
        tasks.spawn(async move {
            let result = create_page(&workers, &dir, &language, issue_id, &page).await;
            (page, result)
        });
    }
    let mut created = HashMap::new();
    let mut failures = Vec::new();
    while let Some(joined) = tasks.join_next().await {
        match joined {
            Ok((page, Ok(sha256))) => {
                created.insert(page.uuid, sha256);
            }
            Ok((page, Err(error))) => failures.push(format!("{}: {error}", page.path)),
            Err(error) => failures.push(format!("a page task failed: {error}")),
        }
    }
    if failures.is_empty() {
        Ok(created)
    } else {
        failures.sort();
        Err(failures.join("; "))
    }
}

/// OCRs, uploads and creates one page; returns its image checksum.
async fn create_page(
    workers: &Workers,
    dir: &Path,
    language: &str,
    issue_id: u64,
    page: &NewPage,
) -> Result<String, String> {
    let image_path = dir.join(&page.path);
    let (sha256, text, hocr) = {
        let _slot = acquire(&workers.cpu).await?;
        let sha256 = sha256_file(image_path.clone())
            .await
            .map_err(|e| e.to_string())?;
        let started = std::time::Instant::now();
        let files = workers
            .ocr
            .ocr(&workers.runtime, &image_path, &sha256, language)
            .await
            .map_err(|e| e.to_string())?;
        debug!("OCR {} in {:.1?}", page.path, started.elapsed());
        (sha256, read_text(&files.text)?, read_text(&files.hocr)?)
    };

    let _upload = acquire(&workers.uploads).await?;
    let image = tokio::fs::read(&image_path)
        .await
        .map_err(|e| e.to_string())?;
    let file_id = workers
        .client
        .upload_page_image(image, &contract::upload_file_name(issue_id, &page.page_no))
        .await
        .map_err(|e| e.to_string())?;
    let body = contract::page_body(&NewPageFields {
        uuid: &page.uuid,
        issue_id,
        page_no: &page.page_no,
        page_sort: &page.page_sort,
        file_id,
        text: &text,
        hocr: &hocr,
    });
    if let Err(error) = workers.client.create_page(&body).await {
        // As with issues: the create may have happened, and the page's UUID says whether.
        let site = fetch(&workers.client, issue_id)
            .await
            .map_err(|lookup| format!("{error}, and checking for the page failed: {lookup}"))?;
        let uuid = page.uuid.to_string();
        if !site.pages.iter().any(|p| p.uuid == uuid) {
            return Err(error.to_string());
        }
    }
    Ok(sha256)
}

fn read_text(path: &Path) -> Result<String, String> {
    fs::read_to_string(path).map_err(|e| format!("{}: {e}", path.display()))
}

async fn fetch(client: &Client, issue_id: u64) -> Result<Issue, String> {
    client
        .issue_by_id(issue_id)
        .await
        .map_err(|e| e.to_string())?
        .ok_or_else(|| format!("issue #{issue_id} is no longer on the site"))
}

/// Creates the state file, or replaces it in one step.
fn write_state(workers: &Workers, dir: &Path, state: &IssueState) -> Result<(), String> {
    let path = dir.join(&*workers.state_file);
    let text = state.to_toml();
    let written = if path.exists() {
        workers.writer.replace(&path, &text)
    } else {
        workers.writer.create(&path, &text)
    };
    written.map_err(|e| e.to_string())
}

/// Installs every missing tile set and PDF. Returns one message per page that failed.
async fn complete_assets(
    workers: &Workers,
    target: &IssueTarget,
    needs: &[AssetNeed],
) -> Vec<String> {
    let mut tasks = JoinSet::new();
    for need in needs.iter().cloned() {
        let workers = workers.clone();
        let target = target.clone();
        tasks.spawn(async move {
            let page = need.page_no.clone();
            install_page_assets(&workers, &target, &need)
                .await
                .map_err(|error| format!("page {page} (#{}): {error}", need.page_id))
        });
    }
    let mut failures = Vec::new();
    while let Some(joined) = tasks.join_next().await {
        match joined {
            Ok(Ok(())) => {}
            Ok(Err(failure)) => failures.push(failure),
            Err(error) => failures.push(format!("a page task failed: {error}")),
        }
    }
    failures.sort();
    failures
}

async fn install_page_assets(
    workers: &Workers,
    target: &IssueTarget,
    need: &AssetNeed,
) -> Result<(), String> {
    let Some(basename) = need.asset_basename.as_deref() else {
        return Err("the site has no image file for it".into());
    };
    let image = target.dir.join(&need.path);
    // One slot for both steps, so a page finishes before later pages start.
    let _slot = acquire(&workers.cpu).await?;

    // A page created in this run was OCR'd already; the cache returns that result.
    let pdf = if need.pdf {
        let files = workers
            .ocr
            .ocr(&workers.runtime, &image, &need.sha256, &target.ocr_language)
            .await
            .map_err(|e| e.to_string())?;
        Some(files.pdf)
    } else {
        None
    };

    let page = PageAssets {
        image: &image,
        basename,
        dzi_dir: need
            .dzi
            .then(|| contract::dzi_dir(target.title_id, target.issue_id)),
        pdf: pdf
            .as_deref()
            .map(|pdf| (pdf, contract::pdf_dir(target.title_id, target.issue_id))),
    };
    workers
        .installer
        .install(&workers.runtime, &page)
        .await
        .map_err(|e| e.to_string())?;
    debug!(
        "installed assets for page {} (#{})",
        need.page_no, need.page_id
    );
    Ok(())
}

async fn acquire(slots: &Arc<Semaphore>) -> Result<OwnedSemaphorePermit, String> {
    Arc::clone(slots)
        .acquire_owned()
        .await
        .map_err(|_| "the run is stopping".to_owned())
}
