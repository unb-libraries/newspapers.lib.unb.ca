//! The run configuration: one TOML file holding every host- and site-specific value.
//!
//! Values that are a contract with the site's own code, such as endpoint paths and where the
//! site looks for DZI tiles and PDFs, live in `adapters::drupal::contract` instead, so the two
//! sides of that contract change together in one commit.

use std::collections::BTreeMap;
use std::ffi::OsString;
use std::fmt;
use std::fs;
use std::os::unix::fs::PermissionsExt;
use std::path::{Component, Path, PathBuf};

use serde::{Deserialize, Serialize};

use crate::domain::page_name::Scheme;

/// Environment variable naming the config file when `--config` is not given.
pub const CONFIG_ENV: &str = "NBNP_INGEST_CONFIG";

#[derive(Debug, Clone, PartialEq, Deserialize, Serialize)]
#[serde(deny_unknown_fields)]
pub struct Config {
    pub site: Site,
    pub paths: Paths,
    pub webtree: Webtree,
    #[serde(default)]
    pub source: Source,
    #[serde(default)]
    pub containers: Containers,
    pub ocr: Ocr,
    pub dzi: Dzi,
    #[serde(default)]
    pub limits: Limits,
    /// Without this section no notifications are sent.
    #[serde(default, skip_serializing_if = "Option::is_none")]
    pub notify: Option<Notify>,
}

#[derive(Debug, Clone, PartialEq, Deserialize, Serialize)]
#[serde(deny_unknown_fields)]
pub struct Site {
    /// Scheme and host of the site, without a trailing slash.
    pub base_url: String,
    /// TOML file with `username` and `password`; must not be readable by group or others.
    pub credentials_file: PathBuf,
    #[serde(default = "defaults::connect_timeout_secs")]
    pub connect_timeout_secs: u64,
    /// Page creates index the page in Solr before responding, so this is generous.
    #[serde(default = "defaults::request_timeout_secs")]
    pub request_timeout_secs: u64,
    #[serde(default = "defaults::retries")]
    pub retries: u32,
    #[serde(default = "defaults::retry_backoff_ms")]
    pub retry_backoff_ms: u64,
}

#[derive(Debug, Clone, PartialEq, Deserialize, Serialize)]
#[serde(deny_unknown_fields)]
pub struct Paths {
    /// Root of the scanned issue trees.
    pub source_root: PathBuf,
    /// The site's public files directory, as mounted on this host.
    pub public_files: PathBuf,
    /// Local scratch space for OCR output and tiles; owned by the user running the tool.
    pub work_dir: PathBuf,
    /// Where run logs, ledgers and audit reports are written.
    pub log_dir: PathBuf,
}

#[derive(Debug, Clone, PartialEq, Deserialize, Serialize)]
#[serde(deny_unknown_fields)]
pub struct Webtree {
    /// Owner of everything the site serves; asset containers run as this user.
    pub owner_uid: u32,
    pub owner_gid: u32,
    /// Where assets are assembled before being renamed into place, relative to `public_files`.
    /// It must be on the same filesystem as the assets so the rename is atomic.
    #[serde(default = "defaults::staging_dir")]
    pub staging_dir: PathBuf,
}

#[derive(Debug, Clone, PartialEq, Deserialize, Serialize)]
#[serde(deny_unknown_fields, default)]
pub struct Source {
    pub issue_file: String,
    pub title_file: String,
    pub legacy_metadata_file: String,
    pub state_file: String,
    /// Written by the PHP importer; read so already-imported issues are not imported again.
    pub legacy_marker: String,
    /// A page image is left out when a file named `<image><skip_suffix>` exists beside it.
    pub skip_suffix: String,
    pub filename_scheme: Scheme,
}

#[derive(Debug, Clone, PartialEq, Deserialize, Serialize)]
#[serde(deny_unknown_fields, default)]
pub struct Containers {
    pub runtime: PathBuf,
    /// Label put on every container, so a run can find and remove its own.
    pub label: String,
    pub pull: PullPolicy,
}

#[derive(Debug, Clone, Copy, PartialEq, Eq, Deserialize, Serialize)]
#[serde(rename_all = "lowercase")]
pub enum PullPolicy {
    Always,
    Missing,
    Never,
}

#[derive(Debug, Clone, PartialEq, Deserialize, Serialize)]
#[serde(deny_unknown_fields)]
pub struct Ocr {
    /// Pin by digest (`image@sha256:...`) so a run is reproducible.
    pub image: String,
    /// Issue metadata language to Tesseract language. The keys are also the languages an issue
    /// may declare; the site accepts `eng` and `fre`.
    pub languages: BTreeMap<String, String>,
    #[serde(default = "defaults::dpi")]
    pub dpi: u32,
    #[serde(default = "defaults::oem")]
    pub oem: u8,
    #[serde(default = "defaults::container_timeout_secs")]
    pub timeout_secs: u64,
}

#[derive(Debug, Clone, PartialEq, Deserialize, Serialize)]
#[serde(deny_unknown_fields)]
pub struct Dzi {
    /// An image with `vips`. Pin by digest.
    pub image: String,
    #[serde(default = "defaults::tile_size")]
    pub tile_size: u32,
    #[serde(default)]
    pub overlap: u32,
    #[serde(default = "defaults::jpeg_quality")]
    pub jpeg_quality: u8,
    #[serde(default = "defaults::container_timeout_secs")]
    pub timeout_secs: u64,
}

#[derive(Debug, Clone, PartialEq, Deserialize, Serialize)]
#[serde(deny_unknown_fields, default)]
pub struct Limits {
    /// Concurrent OCR and tiling processes. OCR is about 80% of an import's run time.
    pub cpu_slots: usize,
    pub issue_concurrency: usize,
    /// Concurrent page uploads across all issues.
    pub upload_concurrency: usize,
    pub min_free_work_mib: u64,
}

#[derive(Debug, Clone, PartialEq, Deserialize, Serialize)]
#[serde(deny_unknown_fields)]
pub struct Notify {
    pub region: String,
    /// Receives the full run summary.
    pub summary_topic_arn: String,
    /// Receives one line per run: `[title:years] import (result)`.
    pub event_topic_arn: String,
}

mod defaults {
    use std::path::PathBuf;

    pub fn connect_timeout_secs() -> u64 {
        10
    }
    pub fn request_timeout_secs() -> u64 {
        300
    }
    pub fn retries() -> u32 {
        4
    }
    pub fn retry_backoff_ms() -> u64 {
        1000
    }
    pub fn staging_dir() -> PathBuf {
        PathBuf::from("serials/.nbnp-ingest-staging")
    }
    pub fn dpi() -> u32 {
        300
    }
    pub fn oem() -> u8 {
        1
    }
    pub fn container_timeout_secs() -> u64 {
        900
    }
    pub fn tile_size() -> u32 {
        256
    }
    pub fn jpeg_quality() -> u8 {
        80
    }
}

impl Default for Source {
    fn default() -> Self {
        Self {
            issue_file: "issue.toml".into(),
            title_file: "title.toml".into(),
            legacy_metadata_file: "metadata.php".into(),
            state_file: ".nbnp_issue.toml".into(),
            legacy_marker: ".nbnp_processed".into(),
            skip_suffix: ".nbnp_skip".into(),
            filename_scheme: Scheme::NbnpV2,
        }
    }
}

impl Default for Containers {
    fn default() -> Self {
        Self {
            runtime: PathBuf::from("docker"),
            label: "ca.unb.lib.nbnp-ingest".into(),
            pull: PullPolicy::Missing,
        }
    }
}

impl Default for Limits {
    fn default() -> Self {
        Self {
            cpu_slots: 4,
            issue_concurrency: 2,
            upload_concurrency: 4,
            min_free_work_mib: 2048,
        }
    }
}

/// A configuration and the file it came from.
#[derive(Debug)]
pub struct Loaded {
    pub config: Config,
    pub path: PathBuf,
}

#[derive(Debug, thiserror::Error)]
pub enum ConfigError {
    #[error("no config file found; looked for {}", display_paths(.searched))]
    NotFound { searched: Vec<PathBuf> },
    #[error("cannot read {path:?}: {source}")]
    Read {
        path: PathBuf,
        source: std::io::Error,
    },
    #[error("{path:?} is not valid: {source}")]
    Parse {
        path: PathBuf,
        source: toml::de::Error,
    },
    #[error("{path:?} has problems:\n  - {}", .problems.join("\n  - "))]
    Invalid {
        path: PathBuf,
        problems: Vec<String>,
    },
}

fn display_paths(paths: &[PathBuf]) -> String {
    paths
        .iter()
        .map(|p| p.display().to_string())
        .collect::<Vec<_>>()
        .join(", ")
}

impl Config {
    /// Finds, reads, parses and validates the config file.
    pub fn load(explicit: Option<&Path>) -> Result<Loaded, ConfigError> {
        let candidates = candidate_paths(explicit, |name| std::env::var_os(name));
        // An explicitly named file must exist; only the fallbacks are optional.
        let path = if explicit.is_some() || std::env::var_os(CONFIG_ENV).is_some() {
            candidates[0].clone()
        } else {
            candidates
                .iter()
                .find(|p| p.is_file())
                .cloned()
                .ok_or(ConfigError::NotFound {
                    searched: candidates.clone(),
                })?
        };
        let text = fs::read_to_string(&path).map_err(|source| ConfigError::Read {
            path: path.clone(),
            source,
        })?;
        let config = Self::parse(&text).map_err(|source| ConfigError::Parse {
            path: path.clone(),
            source,
        })?;
        let problems = config.problems();
        if !problems.is_empty() {
            return Err(ConfigError::Invalid { path, problems });
        }
        Ok(Loaded { config, path })
    }

    pub fn parse(text: &str) -> Result<Self, toml::de::Error> {
        toml::from_str(text)
    }

    /// Lists every value that is present but unusable, so all of them can be fixed at once.
    #[must_use]
    pub fn problems(&self) -> Vec<String> {
        let mut problems = Vec::new();
        let mut require = |ok: bool, problem: &str| {
            if !ok {
                problems.push(problem.to_owned());
            }
        };

        let url = &self.site.base_url;
        require(
            (url.starts_with("https://") || url.starts_with("http://")) && !url.ends_with('/'),
            "site.base_url must start with http:// or https:// and not end with /",
        );
        require(
            self.site.connect_timeout_secs > 0 && self.site.request_timeout_secs > 0,
            "site timeouts must be above 0",
        );
        for (name, path) in [
            ("site.credentials_file", &self.site.credentials_file),
            ("paths.source_root", &self.paths.source_root),
            ("paths.public_files", &self.paths.public_files),
            ("paths.work_dir", &self.paths.work_dir),
            ("paths.log_dir", &self.paths.log_dir),
        ] {
            require(
                path.is_absolute(),
                &format!("{name} must be an absolute path"),
            );
        }
        require(
            is_plain_relative(&self.webtree.staging_dir),
            "webtree.staging_dir must be a relative path without ..",
        );
        for (name, value) in [
            ("source.issue_file", &self.source.issue_file),
            ("source.title_file", &self.source.title_file),
            (
                "source.legacy_metadata_file",
                &self.source.legacy_metadata_file,
            ),
            ("source.state_file", &self.source.state_file),
            ("source.legacy_marker", &self.source.legacy_marker),
            ("source.skip_suffix", &self.source.skip_suffix),
        ] {
            require(
                !value.is_empty() && !value.contains('/'),
                &format!("{name} must be a file name, not empty or a path"),
            );
        }
        require(
            !self.containers.label.is_empty(),
            "containers.label must not be empty",
        );
        require(!self.ocr.image.is_empty(), "ocr.image must not be empty");
        require(!self.dzi.image.is_empty(), "dzi.image must not be empty");
        require(
            !self.ocr.languages.is_empty(),
            "ocr.languages must map at least one language",
        );
        for (from, to) in &self.ocr.languages {
            require(
                is_language_code(from) && is_language_code(to),
                &format!("ocr.languages: {from:?} = {to:?} must both be letters only"),
            );
        }
        require(self.ocr.dpi > 0, "ocr.dpi must be above 0");
        require(self.ocr.oem <= 3, "ocr.oem must be 0 to 3");
        require(self.dzi.tile_size > 0, "dzi.tile_size must be above 0");
        require(
            (1..=100).contains(&self.dzi.jpeg_quality),
            "dzi.jpeg_quality must be 1 to 100",
        );
        require(
            self.ocr.timeout_secs > 0 && self.dzi.timeout_secs > 0,
            "container timeouts must be above 0",
        );
        require(
            self.limits.cpu_slots > 0
                && self.limits.issue_concurrency > 0
                && self.limits.upload_concurrency > 0,
            "limits.cpu_slots, issue_concurrency and upload_concurrency must be above 0",
        );
        problems
    }

    /// Container images not pinned by digest. Allowed, since tags are convenient on a
    /// development machine, but a tag can change under a production run.
    #[must_use]
    pub fn unpinned_images(&self) -> Vec<&str> {
        [self.ocr.image.as_str(), self.dzi.image.as_str()]
            .into_iter()
            .filter(|image| !image.contains("@sha256:"))
            .collect()
    }
}

/// The paths tried, in order: `--config`, then `$NBNP_INGEST_CONFIG`, then
/// `$XDG_CONFIG_HOME/nbnp-ingest/config.toml` (default `~/.config`).
fn candidate_paths(
    explicit: Option<&Path>,
    env: impl Fn(&str) -> Option<OsString>,
) -> Vec<PathBuf> {
    if let Some(path) = explicit {
        return vec![path.to_path_buf()];
    }
    if let Some(path) = env(CONFIG_ENV) {
        return vec![PathBuf::from(path)];
    }
    let config_home = env("XDG_CONFIG_HOME")
        .map(PathBuf::from)
        .filter(|p| p.is_absolute())
        .or_else(|| env("HOME").map(|home| PathBuf::from(home).join(".config")));
    config_home
        .map(|dir| dir.join("nbnp-ingest").join("config.toml"))
        .into_iter()
        .collect()
}

fn is_plain_relative(path: &Path) -> bool {
    !path.as_os_str().is_empty()
        && path
            .components()
            .all(|component| matches!(component, Component::Normal(_)))
}

fn is_language_code(code: &str) -> bool {
    !code.is_empty() && code.bytes().all(|b| b.is_ascii_alphabetic())
}

/// Credentials for the site account that ingests pages.
#[derive(Clone, Deserialize)]
#[serde(deny_unknown_fields)]
pub struct SiteCredentials {
    pub username: String,
    pub password: String,
}

impl fmt::Debug for SiteCredentials {
    fn fmt(&self, f: &mut fmt::Formatter<'_>) -> fmt::Result {
        f.debug_struct("SiteCredentials")
            .field("username", &self.username)
            .field("password", &"<redacted>")
            .finish()
    }
}

#[derive(Debug, thiserror::Error)]
pub enum CredentialsError {
    #[error("cannot read {path:?}: {source}")]
    Read {
        path: PathBuf,
        source: std::io::Error,
    },
    #[error("{path:?} can be read by group or others (mode {mode:o}); run chmod 600 on it")]
    TooOpen { path: PathBuf, mode: u32 },
    /// Only the position and reason: the parser's own message quotes the line, which can be
    /// the password.
    #[error(
        "{path:?} must hold username = \"...\" and password = \"...\" (quoted): line {line}: \
         {reason}"
    )]
    Parse {
        path: PathBuf,
        line: usize,
        reason: String,
    },
}

impl SiteCredentials {
    pub fn load(path: &Path) -> Result<Self, CredentialsError> {
        let read_error = |source| CredentialsError::Read {
            path: path.to_path_buf(),
            source,
        };
        let mode = fs::metadata(path).map_err(read_error)?.permissions().mode() & 0o777;
        if mode & 0o077 != 0 {
            return Err(CredentialsError::TooOpen {
                path: path.to_path_buf(),
                mode,
            });
        }
        let text = fs::read_to_string(path).map_err(read_error)?;
        toml::from_str(&text).map_err(|error: toml::de::Error| CredentialsError::Parse {
            path: path.to_path_buf(),
            line: error
                .span()
                .map_or(0, |span| text[..span.start].matches('\n').count() + 1),
            reason: error.message().to_owned(),
        })
    }
}

#[cfg(test)]
mod tests {
    use super::*;

    const EXAMPLE: &str = include_str!("../config.example.toml");

    #[test]
    fn credential_errors_never_quote_the_file() {
        let dir = tempfile::tempdir().unwrap();
        let path = dir.path().join("creds.toml");
        fs::write(&path, "username = \"ingest\"\npassword = hunter2-secret\n").unwrap();
        fs::set_permissions(&path, fs::Permissions::from_mode(0o600)).unwrap();
        let error = SiteCredentials::load(&path).unwrap_err().to_string();
        assert!(!error.contains("hunter2"), "{error}");
        assert!(error.contains("line 2"), "{error}");
    }

    /// Only the keys without defaults.
    const MINIMAL: &str = r#"
        [site]
        base_url = "https://newspapers.example.org"
        credentials_file = "/etc/nbnp-ingest/site-credentials.toml"
        [paths]
        source_root = "/mnt/nbnp"
        public_files = "/mnt/site/files"
        work_dir = "/var/tmp/nbnp-ingest"
        log_dir = "/var/log/nbnp-ingest"
        [webtree]
        owner_uid = 100
        owner_gid = 102
        [ocr]
        image = "tesseract@sha256:00"
        languages = { eng = "eng" }
        [dzi]
        image = "vips@sha256:00"
    "#;

    fn key_paths(value: &toml::Value, prefix: &str, out: &mut BTreeMap<String, toml::Value>) {
        match value {
            toml::Value::Table(table) => {
                for (key, child) in table {
                    let path = if prefix.is_empty() {
                        key.clone()
                    } else {
                        format!("{prefix}.{key}")
                    };
                    // A map the user fills in; its entries are data, not keys.
                    if path == "ocr.languages" {
                        out.insert(path, child.clone());
                    } else {
                        key_paths(child, &path, out);
                    }
                }
            }
            leaf => {
                out.insert(prefix.to_owned(), leaf.clone());
            }
        }
    }

    fn keys_of(value: &toml::Value) -> BTreeMap<String, toml::Value> {
        let mut out = BTreeMap::new();
        key_paths(value, "", &mut out);
        out
    }

    #[test]
    fn example_is_valid() {
        let config = Config::parse(EXAMPLE).expect("example parses");
        assert_eq!(config.problems(), Vec::<String>::new());
    }

    #[test]
    fn example_documents_every_key() {
        let written = keys_of(&toml::from_str(EXAMPLE).unwrap());
        let resolved = keys_of(&toml::Value::try_from(Config::parse(EXAMPLE).unwrap()).unwrap());
        let missing: Vec<_> = resolved
            .keys()
            .filter(|k| !written.contains_key(*k))
            .collect();
        assert!(missing.is_empty(), "config.example.toml lacks {missing:?}");
    }

    #[test]
    fn example_shows_the_real_defaults() {
        let minimal_written = keys_of(&toml::from_str(MINIMAL).unwrap());
        let defaults = keys_of(&toml::Value::try_from(Config::parse(MINIMAL).unwrap()).unwrap());
        let example = keys_of(&toml::from_str(EXAMPLE).unwrap());
        for (key, default) in defaults
            .iter()
            .filter(|(k, _)| !minimal_written.contains_key(*k))
        {
            assert_eq!(
                example.get(key),
                Some(default),
                "config.example.toml shows a different default for {key}"
            );
        }
    }

    #[test]
    fn unknown_keys_are_rejected() {
        let text = format!("{MINIMAL}\n[limits]\ncpu_slot = 8\n");
        let error = Config::parse(&text).unwrap_err().to_string();
        assert!(error.contains("cpu_slot"), "{error}");
    }

    #[test]
    fn missing_required_key_is_named() {
        let text = MINIMAL.replace("owner_gid = 102", "");
        let error = Config::parse(&text).unwrap_err().to_string();
        assert!(error.contains("owner_gid"), "{error}");
    }

    #[test]
    fn problems_are_all_reported() {
        let mut config = Config::parse(MINIMAL).unwrap();
        config.site.base_url = "https://newspapers.example.org/".into();
        config.paths.work_dir = "relative".into();
        config.webtree.staging_dir = "../escape".into();
        config.limits.cpu_slots = 0;
        config.ocr.languages.insert("fre".into(), "fra; rm".into());
        let problems = config.problems();
        assert_eq!(problems.len(), 5, "{problems:#?}");
    }

    #[test]
    fn unpinned_images_are_listed() {
        let mut config = Config::parse(MINIMAL).unwrap();
        assert!(config.unpinned_images().is_empty());
        config.dzi.image = "vips:latest".into();
        assert_eq!(config.unpinned_images(), vec!["vips:latest"]);
    }

    #[test]
    fn search_order() {
        let env = |vars: &'static [(&'static str, &'static str)]| {
            move |name: &str| {
                vars.iter()
                    .find(|(k, _)| *k == name)
                    .map(|(_, v)| OsString::from(v))
            }
        };
        assert_eq!(
            candidate_paths(Some(Path::new("/a.toml")), env(&[(CONFIG_ENV, "/b.toml")])),
            vec![PathBuf::from("/a.toml")]
        );
        assert_eq!(
            candidate_paths(None, env(&[(CONFIG_ENV, "/b.toml"), ("HOME", "/home/x")])),
            vec![PathBuf::from("/b.toml")]
        );
        assert_eq!(
            candidate_paths(
                None,
                env(&[("XDG_CONFIG_HOME", "/xdg"), ("HOME", "/home/x")])
            ),
            vec![PathBuf::from("/xdg/nbnp-ingest/config.toml")]
        );
        assert_eq!(
            candidate_paths(
                None,
                env(&[("XDG_CONFIG_HOME", "rel"), ("HOME", "/home/x")])
            ),
            vec![PathBuf::from("/home/x/.config/nbnp-ingest/config.toml")]
        );
    }

    #[test]
    fn credentials_must_be_private() {
        let dir = tempfile::tempdir().unwrap();
        let path = dir.path().join("creds.toml");
        fs::write(&path, "username = \"ingest\"\npassword = \"secret\"\n").unwrap();

        fs::set_permissions(&path, fs::Permissions::from_mode(0o640)).unwrap();
        assert!(matches!(
            SiteCredentials::load(&path),
            Err(CredentialsError::TooOpen { mode: 0o640, .. })
        ));

        fs::set_permissions(&path, fs::Permissions::from_mode(0o600)).unwrap();
        let credentials = SiteCredentials::load(&path).unwrap();
        assert_eq!(credentials.username, "ingest");
        assert!(!format!("{credentials:?}").contains("secret"));
    }
}
