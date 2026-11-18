//! Carrying out the plans: one task per issue, sharing the run's CPU and upload slots.

use std::sync::Arc;
use std::time::Instant;

use tokio::sync::{Mutex, Semaphore};
use tokio::task::JoinSet;
use uuid::Uuid;

use crate::adapters::assets::Installer;
use crate::adapters::container::Runtime;
use crate::adapters::drupal::Client;
use crate::adapters::ocr::OcrCache;
use crate::adapters::source_write::SourceWriter;
use crate::config::Config;
use crate::error::Error;
use crate::ingest::execute::{IssueJob, OnSite, Workers, run_issue};

use super::{Action, IssuePlan};

/// Carries out every plan that has work in it. Issues run at the same time, at most
/// `issue_concurrency`, and their pages share the CPU and upload slots, so a slot freed at the
/// end of one issue goes to the next instead of waiting.
/// Returns whether the run was stopped with Ctrl-C.
pub(super) async fn execute(
    config: &Config,
    client: &Client,
    plans: &mut [IssuePlan],
    run_id: &str,
) -> Result<bool, Error> {
    let runtime = Runtime::new(&config.containers, run_id);
    for image in [&config.ocr.image, &config.dzi.image] {
        runtime.ensure_image(image, config.containers.pull).await?;
    }
    let workers = Workers {
        client: client.clone(),
        runtime: Arc::new(runtime),
        ocr: Arc::new(OcrCache::new(&config.paths.work_dir, &config.ocr, run_id)),
        installer: Arc::new(Installer::new(config, run_id)),
        writer: Arc::new(SourceWriter::new(run_id)),
        state_file: config.source.state_file.as_str().into(),
        cpu: Arc::new(Semaphore::new(config.limits.cpu_slots.max(1))),
        uploads: Arc::new(Semaphore::new(config.limits.upload_concurrency.max(1))),
        creates: Arc::new(Mutex::new(())),
    };

    let issues = Arc::new(Semaphore::new(config.limits.issue_concurrency.max(1)));
    let mut tasks = JoinSet::new();
    for (index, plan) in plans.iter_mut().enumerate() {
        let Some(job) = job_for(plan) else {
            continue;
        };
        let (workers, issues) = (workers.clone(), Arc::clone(&issues));
        tasks.spawn(async move {
            let Ok(_issue) = issues.acquire_owned().await else {
                return (index, Err("the run is stopping".to_owned()), None);
            };
            let started = Instant::now();
            let done = run_issue(&workers, &job).await;
            (index, done, Some(started.elapsed()))
        });
    }
    // Ctrl-C stops admitting work: waiting issues and pages end with "the run is stopping",
    // this run's containers are removed, and what finished is still reported. Requests in
    // flight complete, so nothing is left half-created on the site.
    let mut interrupted = false;
    loop {
        tokio::select! {
            joined = tasks.join_next() => match joined {
                Some(Ok((index, done, elapsed))) => {
                    plans[index].done = Some(done);
                    plans[index].elapsed = elapsed;
                }
                Some(Err(error)) => tracing::error!("an issue task failed: {error}"),
                None => break,
            },
            _ = tokio::signal::ctrl_c(), if !interrupted => {
                interrupted = true;
                tracing::warn!("stopping: finishing what is under way, starting nothing new");
                issues.close();
                workers.cpu.close();
                workers.uploads.close();
                workers.runtime.remove_run_containers().await;
            }
        }
    }
    workers.runtime.remove_run_containers().await;
    Ok(interrupted)
}

/// The work a plan calls for, or `None` when there is none. Records in `plan.done` why an issue
/// that needs work cannot be done.
pub(super) fn job_for(plan: &mut IssuePlan) -> Option<IssueJob> {
    let info = plan.info.clone()?;
    let on_site = match plan.action.clone()? {
        Action::Blocked(_) => return None,
        Action::Create { uuid, .. } => OnSite::New {
            uuid,
            metadata: info.metadata.clone(),
        },
        Action::Update { site_id, .. } => {
            let Some(Ok(uuid)) = info.site_uuid.as_deref().map(Uuid::parse_str) else {
                plan.done = Some(Err("the site issue's UUID is not a UUID".into()));
                return None;
            };
            OnSite::Existing {
                issue_id: site_id,
                uuid,
            }
        }
    };
    let pages = &plan.pages;
    if matches!(on_site, OnSite::Existing { .. })
        && pages.create.is_empty()
        && pages.assets.is_empty()
        && info.state_complete
    {
        return None;
    }
    let Some(ocr_language) = plan.ocr_language.clone() else {
        plan.done = Some(Err("no OCR language is configured for it".into()));
        return None;
    };
    Some(IssueJob {
        dir: info.dir,
        title_id: info.title_id,
        ocr_language,
        on_site,
        create: pages.create.clone(),
        assets: pages.assets.clone(),
        state_complete: info.state_complete,
        source_checksums: info.source_checksums,
    })
}
