//! Reads an issue directory's metadata the one way `check`, `convert` and `import` all use:
//! `issue.toml` when present, otherwise the legacy `metadata.php`, either one completed by the
//! nearest `title.toml` above it.

use std::collections::HashMap;
use std::fs;
use std::path::{Path, PathBuf};

use crate::adapters::source_tree::{IssueDir, TitleFiles};
use crate::config::Source;
use crate::domain::finding::Finding;
use crate::domain::metadata::legacy::{self, Legacy};
use crate::domain::metadata::{IssueFields, IssueMetadata, TitleFile};

/// Title files, each read and parsed once however many issues share it.
#[derive(Debug, Default)]
pub struct Titles {
    files: TitleFiles,
    parsed: HashMap<PathBuf, Result<TitleFile, String>>,
}

impl Titles {
    /// The title file above `issue_dir`, if there is one. The error names the file.
    pub fn above(
        &mut self,
        issue_dir: &Path,
        names: &Source,
    ) -> Result<Option<(PathBuf, TitleFile)>, String> {
        let Some(path) = self.files.above(issue_dir, &names.title_file) else {
            return Ok(None);
        };
        let parsed = self.parsed.entry(path.clone()).or_insert_with(|| {
            fs::read_to_string(&path)
                .map_err(|error| error.to_string())
                .and_then(|text| TitleFile::parse(&text).map_err(|error| error.to_string()))
        });
        match parsed {
            Ok(title) => Ok(Some((path, title.clone()))),
            Err(error) => Err(format!("{}: {}", path.display(), error.trim_end())),
        }
    }
}

/// Which file an issue's own values came from.
#[derive(Debug, Clone, Copy, PartialEq, Eq)]
pub enum Origin {
    IssueFile,
    LegacyMetadata,
}

/// An issue's metadata, or why there is none, with anything else worth reporting about it.
#[derive(Debug)]
pub struct Read {
    pub metadata: Option<IssueMetadata>,
    pub origin: Origin,
    pub findings: Vec<Finding>,
}

/// Reads and resolves the metadata of `issue`, and checks it against the site's limits and the
/// configured languages.
pub fn read_metadata(
    issue: &IssueDir,
    names: &Source,
    titles: &mut Titles,
    languages: &[&str],
) -> Read {
    let mut findings = Vec::new();
    let title = match titles.above(&issue.path, names) {
        Ok(found) => found.map(|(_, title)| title),
        Err(error) => {
            findings.push(Finding::error(format!("title file {error}")));
            return Read {
                metadata: None,
                origin: origin(issue),
                findings,
            };
        }
    };

    let metadata = if issue.metadata_files.has_issue_file() {
        let metadata = read_issue_file(&issue.path.join(&names.issue_file), title.as_ref());
        if issue.metadata_files.has_legacy() {
            findings.extend(compare_legacy(
                issue,
                names,
                title.as_ref(),
                metadata.as_ref().ok(),
            ));
        }
        metadata
    } else {
        read_legacy(&issue.path.join(&names.legacy_metadata_file)).and_then(|legacy| {
            findings.extend(
                legacy
                    .warnings
                    .iter()
                    .map(|w| Finding::warning(format!("{}, {w}", names.legacy_metadata_file))),
            );
            legacy
                .fields
                .resolve(title.as_ref())
                .map_err(|missing| format!("{}: {missing}", names.legacy_metadata_file))
        })
    };

    let metadata = match metadata {
        Ok(metadata) => {
            findings.extend(metadata.problems(languages).into_iter().map(Finding::error));
            Some(metadata)
        }
        Err(error) => {
            findings.push(Finding::error(error));
            None
        }
    };
    Read {
        metadata,
        origin: origin(issue),
        findings,
    }
}

fn origin(issue: &IssueDir) -> Origin {
    if issue.metadata_files.has_issue_file() {
        Origin::IssueFile
    } else {
        Origin::LegacyMetadata
    }
}

fn read_issue_file(path: &Path, title: Option<&TitleFile>) -> Result<IssueMetadata, String> {
    let name = path
        .file_name()
        .map_or_else(String::new, |n| n.to_string_lossy().into_owned());
    let text = fs::read_to_string(path).map_err(|error| format!("{name}: {error}"))?;
    IssueFields::parse(&text)
        .map_err(|error| format!("{name}: {}", error.to_string().trim_end()))?
        .resolve(title)
        .map_err(|missing| format!("{name}: {missing}"))
}

/// Reads a legacy `metadata.php`. The error starts with the file name.
pub fn read_legacy(path: &Path) -> Result<Legacy, String> {
    let name = path
        .file_name()
        .map_or_else(String::new, |n| n.to_string_lossy().into_owned());
    let bytes = fs::read(path).map_err(|error| format!("{name}: {error}"))?;
    legacy::parse(&bytes).map_err(|error| format!("{name}: {error}"))
}

/// When both files exist, `issue.toml` is used; say so if `metadata.php` now says otherwise.
fn compare_legacy(
    issue: &IssueDir,
    names: &Source,
    title: Option<&TitleFile>,
    used: Option<&IssueMetadata>,
) -> Option<Finding> {
    let legacy_name = &names.legacy_metadata_file;
    let issue_name = &names.issue_file;
    let legacy = match read_legacy(&issue.path.join(legacy_name)) {
        Ok(legacy) => legacy.fields.resolve(title),
        Err(error) => {
            return Some(Finding::warning(format!(
                "{error} (unused: {issue_name} is used)"
            )));
        }
    };
    let (Ok(legacy), Some(used)) = (legacy, used) else {
        return None;
    };
    let differences = used.differences(&legacy);
    (!differences.is_empty()).then(|| {
        Finding::warning(format!(
            "{legacy_name} differs from {issue_name}, which is used: {}",
            differences.join("; ")
        ))
    })
}

#[cfg(test)]
mod tests {
    use super::*;
    use crate::adapters::source_tree;
    use crate::domain::finding::Severity;

    const LEGACY: &str = "define('ISSUE_DATE', mktime(0, 0, 0, 29, 1, 1909));
define('ISSUE_TITLE','The News');
define('ISSUE_VOLUME', '33');
define('ISSUE_ISSUE', '5');
define('ISSUE_EDITION', '');
define('ISSUE_SUPPLEMENT_TITLE', '');
define('MISSING_PAGES', '');
define('ISSUE_ERRATA','');
define('ISSUE_LANGUAGE', 'eng');
define('SOURCE_MEDIA','print');
";

    fn write(root: &Path, path: &str, contents: &str) {
        let path = root.join(path);
        fs::create_dir_all(path.parent().unwrap()).unwrap();
        fs::write(path, contents).unwrap();
    }

    fn read_all(root: &Path) -> Vec<Read> {
        let names = Source::default();
        let tree = source_tree::scan(root, &names).unwrap();
        let mut titles = Titles::default();
        tree.issues
            .iter()
            .map(|issue| read_metadata(issue, &names, &mut titles, &["eng", "fre"]))
            .collect()
    }

    #[test]
    fn legacy_metadata_takes_the_title_id_from_title_toml() {
        let dir = tempfile::tempdir().unwrap();
        write(
            dir.path(),
            "title.toml",
            "title_id = 48\ntitle = \"The News\"",
        );
        write(dir.path(), "TN_1909_33_05/metadata.php", LEGACY);
        let reads = read_all(dir.path());
        assert!(reads[0].findings.is_empty(), "{:?}", reads[0].findings);
        let metadata = reads[0].metadata.as_ref().unwrap();
        assert_eq!(metadata.title_id, Some(48));
        assert_eq!(metadata.date.to_string(), "1909-01-29");
        assert_eq!(reads[0].origin, Origin::LegacyMetadata);
    }

    #[test]
    fn issue_toml_wins_and_differences_are_reported() {
        let dir = tempfile::tempdir().unwrap();
        write(
            dir.path(),
            "title.toml",
            "title_id = 48\ntitle = \"The News\"\nlanguage = \"eng\"\nmedia = \"print\"",
        );
        write(dir.path(), "I/metadata.php", LEGACY);
        write(
            dir.path(),
            "I/issue.toml",
            "date = 1909-01-29\nvolume = \"33\"\nissue = \"6\"",
        );
        let reads = read_all(dir.path());
        assert_eq!(reads[0].origin, Origin::IssueFile);
        assert_eq!(reads[0].metadata.as_ref().unwrap().issue, "6");
        assert_eq!(reads[0].findings.len(), 1);
        assert_eq!(reads[0].findings[0].severity, Severity::Warning);
        assert!(
            reads[0].findings[0]
                .message
                .contains("issue: \"6\" vs \"5\"")
        );
    }

    #[test]
    fn a_broken_title_file_is_an_error_for_its_issues() {
        let dir = tempfile::tempdir().unwrap();
        write(dir.path(), "title.toml", "title_id = \"48\"");
        write(dir.path(), "A/metadata.php", LEGACY);
        write(dir.path(), "B/metadata.php", LEGACY);
        let reads = read_all(dir.path());
        for read in reads {
            assert!(read.metadata.is_none());
            assert!(read.findings[0].message.starts_with("title file "));
        }
    }

    #[test]
    fn values_the_site_refuses_are_errors() {
        let dir = tempfile::tempdir().unwrap();
        write(
            dir.path(),
            "A/metadata.php",
            &LEGACY.replace("'eng'", "'deu'"),
        );
        let reads = read_all(dir.path());
        assert!(reads[0].findings[0].message.contains("\"deu\""));
        assert_eq!(reads[0].findings[0].severity, Severity::Error);
    }
}
