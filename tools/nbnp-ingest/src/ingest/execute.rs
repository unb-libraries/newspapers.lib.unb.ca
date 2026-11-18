//! Carries out the asset part of an issue's plan: OCR for missing PDFs, tiles, installation.
//!
//! OCR and tiling each hold one CPU slot while they run, so `limits.cpu_slots` bounds the
//! processes across all pages and issues of a run.

use std::path::PathBuf;
use std::sync::Arc;

use tokio::sync::Semaphore;
use tokio::task::JoinSet;
use tracing::{debug, info};

use crate::adapters::assets::{Installer, PageAssets};
use crate::adapters::container::Runtime;
use crate::adapters::drupal::contract;
use crate::adapters::ocr::OcrCache;
use crate::domain::plan::AssetNeed;

/// What the pages of a run share.
#[derive(Debug, Clone)]
pub struct Workers {
    pub runtime: Arc<Runtime>,
    pub ocr: Arc<OcrCache>,
    pub installer: Arc<Installer>,
    pub cpu: Arc<Semaphore>,
}

/// Where an issue is, on both sides.
#[derive(Debug, Clone)]
pub struct IssueTarget {
    pub dir: PathBuf,
    pub title_id: u32,
    pub issue_id: u64,
    /// Tesseract's name for the issue's language.
    pub ocr_language: String,
}

/// Installs every missing tile set and PDF. Returns one message per page that failed.
pub async fn complete_assets(
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
            complete_page(&workers, &target, &need)
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

async fn complete_page(
    workers: &Workers,
    target: &IssueTarget,
    need: &AssetNeed,
) -> Result<(), String> {
    let Some(basename) = need.asset_basename.as_deref() else {
        return Err("the site has no image file for it".into());
    };
    let image = target.dir.join(&need.path);

    let pdf = if need.pdf {
        let _slot = acquire(&workers.cpu).await?;
        let started = std::time::Instant::now();
        let files = workers
            .ocr
            .ocr(&workers.runtime, &image, &need.sha256, &target.ocr_language)
            .await
            .map_err(|e| e.to_string())?;
        debug!("OCR {} in {:.1?}", need.path, started.elapsed());
        Some(files.pdf)
    } else {
        None
    };

    let _slot = acquire(&workers.cpu).await?;
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
    info!(
        "installed {}{} for page {} (#{})",
        if need.dzi { "tiles" } else { "" },
        match (need.dzi, need.pdf) {
            (true, true) => " and PDF",
            (false, true) => "PDF",
            _ => "",
        },
        need.page_no,
        need.page_id
    );
    Ok(())
}

async fn acquire(cpu: &Arc<Semaphore>) -> Result<tokio::sync::OwnedSemaphorePermit, String> {
    Arc::clone(cpu)
        .acquire_owned()
        .await
        .map_err(|_| "the run is stopping".to_owned())
}

/// Removes the OCR entries of pages whose PDF is now on the site.
pub fn forget_ocr(workers: &Workers, target: &IssueTarget, needs: &[AssetNeed]) {
    for need in needs.iter().filter(|n| n.pdf) {
        workers.ocr.remove(&need.sha256, &target.ocr_language);
    }
}
