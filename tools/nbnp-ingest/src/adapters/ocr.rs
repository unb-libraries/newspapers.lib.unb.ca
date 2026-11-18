//! OCR with Tesseract: one run per page writes the hOCR, the plain text and the page PDF.
//!
//! Results are kept in the work directory, one entry per image checksum, language and OCR
//! settings, so a page is never OCR'd twice, even across failed runs. An entry is written to a
//! temporary directory and renamed into place once all three files exist, so an entry that
//! exists is complete.

use std::fmt::Write as _;
use std::fs;
use std::io;
use std::path::{Path, PathBuf};
use std::sync::atomic::{AtomicU64, Ordering};
use std::time::Duration;

use sha2::{Digest, Sha256};

use super::container::{ContainerError, Mount, Runtime, Spec};
use crate::config::Ocr as OcrConfig;

/// The three files of one OCR entry.
#[derive(Debug, Clone, PartialEq, Eq)]
pub struct OcrFiles {
    pub hocr: PathBuf,
    pub text: PathBuf,
    pub pdf: PathBuf,
}

#[derive(Debug, thiserror::Error)]
pub enum OcrError {
    #[error("{path}: {source}")]
    Io { path: PathBuf, source: io::Error },
    #[error(transparent)]
    Container(#[from] ContainerError),
    #[error("tesseract did not write {0}")]
    Missing(PathBuf),
}

/// Output base name inside an entry; Tesseract adds `.hocr`, `.txt` and `.pdf`.
const BASE: &str = "page";

#[derive(Debug)]
pub struct OcrCache {
    root: PathBuf,
    config: OcrConfig,
    /// Distinguishes entries made with different images or settings.
    settings: String,
    user: (u32, u32),
    next_temp: AtomicU64,
    run_id: String,
}

impl OcrCache {
    /// `work_dir/ocr` holds the entries.
    #[must_use]
    pub fn new(work_dir: &Path, config: &OcrConfig, run_id: &str) -> Self {
        let mut hasher = Sha256::new();
        hasher.update(config.image.as_bytes());
        let image: String = hasher.finalize()[..4]
            .iter()
            .fold(String::new(), |mut s, b| {
                let _ = write!(s, "{b:02x}");
                s
            });
        Self {
            root: work_dir.join("ocr"),
            settings: format!("oem{}-dpi{}-{image}", config.oem, config.dpi),
            config: config.clone(),
            // Files the container writes belong to whoever runs the tool.
            user: (
                rustix::process::getuid().as_raw(),
                rustix::process::getgid().as_raw(),
            ),
            next_temp: AtomicU64::new(1),
            run_id: run_id.to_owned(),
        }
    }

    /// The entry for an image and Tesseract language, whether or not it exists yet.
    #[must_use]
    pub fn entry(&self, sha256: &str, language: &str) -> OcrFiles {
        let dir = self
            .root
            .join(format!("{sha256}-{language}-{}", self.settings));
        files_in(&dir)
    }

    /// OCRs `image` unless its entry already exists, and returns the entry's files.
    pub async fn ocr(
        &self,
        runtime: &Runtime,
        image: &Path,
        sha256: &str,
        language: &str,
    ) -> Result<OcrFiles, OcrError> {
        let entry = self.entry(sha256, language);
        let entry_dir = parent(&entry.hocr);
        if entry_dir.is_dir() {
            return Ok(entry);
        }
        let temp = self.root.join(format!(
            ".tmp-{}-{}",
            self.run_id,
            self.next_temp.fetch_add(1, Ordering::Relaxed)
        ));
        let io_error = |path: &Path| {
            let path = path.to_owned();
            move |source| OcrError::Io { path, source }
        };
        fs::create_dir_all(&temp).map_err(io_error(&temp))?;
        let result = self.run(runtime, image, &temp, language).await;
        if let Err(error) = result {
            let _ = fs::remove_dir_all(&temp);
            return Err(error);
        }
        let made = files_in(&temp);
        for file in [&made.hocr, &made.text, &made.pdf] {
            if !file.metadata().is_ok_and(|m| m.len() > 0) {
                let _ = fs::remove_dir_all(&temp);
                return Err(OcrError::Missing(file.clone()));
            }
        }
        match fs::rename(&temp, entry_dir) {
            Ok(()) => Ok(entry),
            // Another task finished the same entry first; theirs is as good.
            Err(_) if entry_dir.is_dir() => {
                let _ = fs::remove_dir_all(&temp);
                Ok(entry)
            }
            Err(source) => {
                let _ = fs::remove_dir_all(&temp);
                Err(OcrError::Io {
                    path: entry_dir.to_owned(),
                    source,
                })
            }
        }
    }

    async fn run(
        &self,
        runtime: &Runtime,
        image: &Path,
        out: &Path,
        language: &str,
    ) -> Result<(), OcrError> {
        let name = image
            .file_name()
            .map(|n| n.to_string_lossy().into_owned())
            .unwrap_or_default();
        let spec = Spec {
            image: self.config.image.clone(),
            user: Some(self.user),
            mounts: vec![
                Mount {
                    host: parent(image).to_owned(),
                    target: "/in".into(),
                    read_only: true,
                },
                Mount {
                    host: out.to_owned(),
                    target: "/out".into(),
                    read_only: false,
                },
            ],
            // One thread per run: `limits.cpu_slots` runs at once use the CPUs.
            env: vec![("OMP_THREAD_LIMIT".into(), "1".into())],
            entrypoint: Some("tesseract".into()),
            args: [
                format!("/in/{name}"),
                format!("/out/{BASE}"),
                "--oem".into(),
                self.config.oem.to_string(),
                "--dpi".into(),
                self.config.dpi.to_string(),
                "-l".into(),
                language.into(),
                "hocr".into(),
                "txt".into(),
                "pdf".into(),
            ]
            .into_iter()
            .map(Into::into)
            .collect(),
            timeout: Duration::from_secs(self.config.timeout_secs),
        };
        runtime.run(&spec).await?;
        Ok(())
    }

    /// Removes an entry, once its page is on the site with its PDF.
    pub fn remove(&self, sha256: &str, language: &str) {
        let entry = self.entry(sha256, language);
        let _ = fs::remove_dir_all(parent(&entry.hocr));
    }
}

fn files_in(dir: &Path) -> OcrFiles {
    OcrFiles {
        hocr: dir.join(format!("{BASE}.hocr")),
        text: dir.join(format!("{BASE}.txt")),
        pdf: dir.join(format!("{BASE}.pdf")),
    }
}

fn parent(path: &Path) -> &Path {
    path.parent().unwrap_or(Path::new("/"))
}

#[cfg(test)]
mod tests {
    use super::*;

    fn config(image: &str) -> OcrConfig {
        toml::from_str(&format!(
            "image = \"{image}\"\nlanguages = {{ eng = \"eng\" }}"
        ))
        .unwrap()
    }

    #[test]
    fn entries_depend_on_image_language_and_settings() {
        let work = Path::new("/work");
        let a = OcrCache::new(work, &config("tesseract@sha256:aa"), "r");
        let b = OcrCache::new(work, &config("tesseract@sha256:bb"), "r");
        let eng = a.entry("abc", "eng");
        assert!(eng.hocr.starts_with("/work/ocr"));
        assert!(eng.hocr.ends_with("page.hocr"));
        assert_ne!(eng, a.entry("abc", "fra"));
        assert_ne!(eng, b.entry("abc", "eng"));
        assert_eq!(eng, a.entry("abc", "eng"));
    }

    #[test]
    fn an_entry_exists_only_as_a_directory() {
        let work = tempfile::tempdir().unwrap();
        let cache = OcrCache::new(work.path(), &config("t"), "r");
        let dir = parent(&cache.entry("abc", "eng").hocr).to_owned();
        fs::create_dir_all(&dir).unwrap();
        cache.remove("abc", "eng");
        assert!(!dir.exists());
    }
}
