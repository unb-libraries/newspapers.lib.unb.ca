//! Finds issue directories under a path and lists their page images.
//!
//! An issue directory is one holding an `issue.toml` or a legacy `metadata.php`. Its pages are
//! the `.jpg` files in it and its subdirectories, except subdirectories that are issues
//! themselves. Each directory is listed once; each page image costs one `stat`.

use std::collections::HashMap;
use std::fs;
use std::io;
use std::path::{Path, PathBuf};

use crate::config::Source;
use crate::domain::finding::Finding;
use crate::domain::pages::SourcePage;

/// The only page image extension the site handles.
pub const PAGE_EXTENSION: &str = "jpg";

#[derive(Debug, Default)]
pub struct Tree {
    pub issues: Vec<IssueDir>,
    /// Problems outside any issue directory, such as an unreadable directory.
    pub problems: Vec<Finding>,
}

#[derive(Debug)]
pub struct IssueDir {
    pub path: PathBuf,
    pub metadata_files: MetadataFiles,
    pub import_marker: ImportMarker,
    /// Whether this process may create files here, as import and convert must.
    pub writable: bool,
    /// Page images to import, in no particular order.
    pub pages: Vec<SourcePage>,
    /// Images left out by a skip marker, relative to the issue directory.
    pub skipped: Vec<String>,
    /// The issue directory this one is inside, if any.
    pub parent_issue: Option<PathBuf>,
    pub problems: Vec<Finding>,
}

/// Which metadata files an issue directory holds.
#[derive(Debug, Clone, Copy, PartialEq, Eq)]
pub enum MetadataFiles {
    IssueFile,
    LegacyOnly,
    /// `issue.toml` is used; `metadata.php` is only compared with it.
    Both,
}

impl MetadataFiles {
    #[must_use]
    pub fn has_issue_file(self) -> bool {
        matches!(self, Self::IssueFile | Self::Both)
    }

    #[must_use]
    pub fn has_legacy(self) -> bool {
        matches!(self, Self::LegacyOnly | Self::Both)
    }
}

/// What the directory says about earlier imports.
#[derive(Debug, Clone, Copy, PartialEq, Eq)]
pub enum ImportMarker {
    None,
    /// The PHP importer's empty `.nbnp_processed`.
    Legacy,
    /// This tool's state file, whether or not the PHP marker is there too.
    State,
}

/// Walks `root`, which may itself be an issue directory, in name order.
pub fn scan(root: &Path, names: &Source) -> io::Result<Tree> {
    let mut walker = Walker {
        names,
        tree: Tree::default(),
    };
    let entries = read_sorted(root)?;
    walker.visit(root, entries, None);
    Ok(walker.tree)
}

struct Walker<'a> {
    names: &'a Source,
    tree: Tree,
}

/// One directory entry. `kind` comes from the directory listing, without a `stat`.
struct Entry {
    name: String,
    path: PathBuf,
    kind: fs::FileType,
}

impl Walker<'_> {
    /// `owner` is the index of the issue that pages found here belong to.
    fn visit(&mut self, dir: &Path, entries: Vec<io::Result<Entry>>, owner: Option<usize>) {
        let mut listed = Vec::with_capacity(entries.len());
        for entry in entries {
            match entry {
                Ok(entry) => listed.push(entry),
                Err(error) => self.problem(owner, format!("{}: {error}", dir.display())),
            }
        }
        let has = |name: &str| listed.iter().any(|e| e.name == name && e.kind.is_file());

        let metadata_files = match (
            has(&self.names.issue_file),
            has(&self.names.legacy_metadata_file),
        ) {
            (true, true) => Some(MetadataFiles::Both),
            (true, false) => Some(MetadataFiles::IssueFile),
            (false, true) => Some(MetadataFiles::LegacyOnly),
            (false, false) => None,
        };
        let owner = if let Some(metadata_files) = metadata_files {
            let import_marker = if has(&self.names.state_file) {
                ImportMarker::State
            } else if has(&self.names.legacy_marker) {
                ImportMarker::Legacy
            } else {
                ImportMarker::None
            };
            let parent_issue = owner.map(|i| self.tree.issues[i].path.clone());
            self.tree.issues.push(IssueDir {
                path: dir.to_owned(),
                metadata_files,
                import_marker,
                writable: rustix::fs::access(dir, rustix::fs::Access::WRITE_OK).is_ok(),
                pages: Vec::new(),
                skipped: Vec::new(),
                parent_issue,
                problems: Vec::new(),
            });
            Some(self.tree.issues.len() - 1)
        } else {
            owner
        };

        let markers: Vec<&str> = listed
            .iter()
            .filter_map(|e| e.name.strip_suffix(self.names.skip_suffix.as_str()))
            .collect();
        for entry in &listed {
            if entry.kind.is_dir() {
                match read_sorted(&entry.path) {
                    Ok(children) => self.visit(&entry.path, children, owner),
                    Err(error) => {
                        self.problem(owner, format!("{}: {error}", entry.path.display()));
                    }
                }
            } else if let Some(issue) = owner {
                self.file(issue, entry, &markers);
            }
        }
        if let Some(issue) = owner {
            for marker in markers {
                if !listed.iter().any(|e| e.name == marker) {
                    let path = self.relative(issue, &dir.join(marker));
                    self.tree.issues[issue]
                        .problems
                        .push(Finding::warning(format!(
                            "skip marker for {path}, which does not exist"
                        )));
                }
            }
        }
    }

    fn file(&mut self, issue: usize, entry: &Entry, markers: &[&str]) {
        let Some((stem, extension)) = entry.name.rsplit_once('.') else {
            return;
        };
        // Hidden files were never pages, as in the PHP importer.
        if stem.is_empty() || entry.name.starts_with('.') {
            return;
        }
        if !extension.eq_ignore_ascii_case(PAGE_EXTENSION) {
            return;
        }
        let path = self.relative(issue, &entry.path);
        let issue = &mut self.tree.issues[issue];
        if extension != PAGE_EXTENSION {
            issue.problems.push(Finding::error(format!(
                "{path}: rename to .{PAGE_EXTENSION}; the site only handles lowercase \
                 .{PAGE_EXTENSION}"
            )));
        } else if entry.kind.is_symlink() {
            issue
                .problems
                .push(Finding::error(format!("{path} is a symbolic link")));
        } else if markers.contains(&entry.name.as_str()) {
            issue.skipped.push(path);
        } else {
            match entry.path.symlink_metadata() {
                Ok(meta) => issue.pages.push(SourcePage {
                    path,
                    len: meta.len(),
                }),
                Err(error) => issue
                    .problems
                    .push(Finding::error(format!("{path}: {error}"))),
            }
        }
    }

    fn relative(&self, issue: usize, path: &Path) -> String {
        let base = &self.tree.issues[issue].path;
        let relative = path.strip_prefix(base).unwrap_or(path);
        relative
            .components()
            .map(|c| c.as_os_str().to_string_lossy())
            .collect::<Vec<_>>()
            .join("/")
    }

    fn problem(&mut self, owner: Option<usize>, message: String) {
        let finding = Finding::error(message);
        match owner {
            Some(issue) => self.tree.issues[issue].problems.push(finding),
            None => self.tree.problems.push(finding),
        }
    }
}

/// The entries of `dir` sorted by name. Names that are not UTF-8 are errors: they cannot be
/// reported or sent to the site faithfully.
fn read_sorted(dir: &Path) -> io::Result<Vec<io::Result<Entry>>> {
    let mut entries: Vec<io::Result<Entry>> = fs::read_dir(dir)?
        .map(|entry| {
            let entry = entry?;
            let name = entry.file_name().into_string().map_err(|name| {
                io::Error::new(
                    io::ErrorKind::InvalidData,
                    format!("{} is not a UTF-8 file name", name.display()),
                )
            })?;
            Ok(Entry {
                path: entry.path(),
                kind: entry.file_type()?,
                name,
            })
        })
        .collect();
    entries.sort_by(|a, b| match (a, b) {
        (Ok(a), Ok(b)) => a.name.cmp(&b.name),
        (Ok(_), Err(_)) => std::cmp::Ordering::Less,
        (Err(_), Ok(_)) => std::cmp::Ordering::Greater,
        (Err(_), Err(_)) => std::cmp::Ordering::Equal,
    });
    Ok(entries)
}

/// Finds the nearest title file above each issue directory, remembering every directory looked
/// at, so a title with thousands of issues costs one lookup per directory.
#[derive(Debug, Default)]
pub struct TitleFiles {
    nearest: HashMap<PathBuf, Option<PathBuf>>,
}

impl TitleFiles {
    /// The title file in the closest directory above `issue_dir`, not counting `issue_dir`.
    pub fn above(&mut self, issue_dir: &Path, file_name: &str) -> Option<PathBuf> {
        let parent = issue_dir.parent()?;
        self.nearest_from(parent, file_name)
    }

    fn nearest_from(&mut self, dir: &Path, file_name: &str) -> Option<PathBuf> {
        if let Some(found) = self.nearest.get(dir) {
            return found.clone();
        }
        let candidate = dir.join(file_name);
        let found = if candidate.is_file() {
            Some(candidate)
        } else {
            dir.parent().and_then(|up| self.nearest_from(up, file_name))
        };
        self.nearest.insert(dir.to_owned(), found.clone());
        found
    }
}

#[cfg(test)]
mod tests {
    use super::*;

    fn touch(root: &Path, path: &str, contents: &str) {
        let path = root.join(path);
        fs::create_dir_all(path.parent().unwrap()).unwrap();
        fs::write(path, contents).unwrap();
    }

    #[test]
    fn finds_issues_pages_and_skips() {
        let dir = tempfile::tempdir().unwrap();
        let root = dir.path();
        touch(root, "title.toml", "title_id = 1");
        touch(root, "A/metadata.php", "");
        touch(root, "A/001_T_1909_1_1_01.jpg", "x");
        touch(root, "A/001_T_1909_1_1_02.jpg", "x");
        touch(root, "A/001_T_1909_1_1_02.jpg.nbnp_skip", "");
        touch(root, "A/gone.jpg.nbnp_skip", "");
        touch(root, "A/.hidden.jpg", "x");
        touch(root, "A/notes.txt", "x");
        touch(root, "A/UPPER_T_1909_1_1_03.JPG", "x");
        touch(root, "A/sub/001_T_1909_1_1_04.jpg", "");
        touch(root, "A/A_Sup/issue.toml", "");
        touch(root, "A/A_Sup/001_T_1909_1_1_Sup_01.jpg", "x");
        touch(root, "B/001_T_1909_1_1_01.jpg", "x");

        let tree = scan(root, &Source::default()).unwrap();
        assert!(tree.problems.is_empty());
        assert_eq!(tree.issues.len(), 2);

        let a = &tree.issues[0];
        assert_eq!(a.path, root.join("A"));
        assert_eq!(a.metadata_files, MetadataFiles::LegacyOnly);
        assert!(a.writable);
        let pages: Vec<_> = a.pages.iter().map(|p| (p.path.as_str(), p.len)).collect();
        assert_eq!(
            pages,
            [
                ("001_T_1909_1_1_01.jpg", 1),
                ("sub/001_T_1909_1_1_04.jpg", 0)
            ]
        );
        assert_eq!(a.skipped, ["001_T_1909_1_1_02.jpg"]);
        let problems: Vec<_> = a.problems.iter().map(|p| p.message.as_str()).collect();
        assert_eq!(problems.len(), 2, "{problems:?}");
        assert!(problems[0].starts_with("UPPER_T_1909_1_1_03.JPG: rename to .jpg"));
        assert!(problems[1].contains("skip marker for gone.jpg"));

        let sup = &tree.issues[1];
        assert_eq!(sup.parent_issue.as_deref(), Some(root.join("A").as_path()));
        assert_eq!(sup.pages.len(), 1);

        let mut titles = TitleFiles::default();
        assert_eq!(
            titles.above(&sup.path, "title.toml"),
            Some(root.join("title.toml"))
        );
        assert_eq!(
            titles.above(&a.path, "title.toml"),
            Some(root.join("title.toml"))
        );
    }

    #[test]
    fn the_root_may_be_an_issue() {
        let dir = tempfile::tempdir().unwrap();
        touch(dir.path(), "issue.toml", "");
        touch(dir.path(), "001_T_1909_1_1_01.jpg", "x");
        let tree = scan(dir.path(), &Source::default()).unwrap();
        assert_eq!(tree.issues.len(), 1);
        assert_eq!(tree.issues[0].pages.len(), 1);
    }
}
