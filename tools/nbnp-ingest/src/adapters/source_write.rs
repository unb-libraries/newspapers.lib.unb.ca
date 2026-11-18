//! Writes small files into issue directories in the source tree.
//!
//! The file is written beside its destination under a hidden temporary name and hard-linked into
//! place. The link fails if the destination exists, so an existing file is never replaced, and a
//! reader never sees half a file.

use std::fs;
use std::io;
use std::path::{Path, PathBuf};

#[derive(Debug, thiserror::Error)]
pub enum WriteError {
    #[error("{0} already exists")]
    Exists(PathBuf),
    #[error("cannot write {path}: {source}")]
    Io { path: PathBuf, source: io::Error },
}

#[derive(Debug)]
pub struct SourceWriter {
    run_id: String,
}

impl SourceWriter {
    #[must_use]
    pub fn new(run_id: &str) -> Self {
        Self {
            run_id: run_id.to_owned(),
        }
    }

    /// Writes `contents` to `dest`, refusing to replace an existing file.
    pub fn create(&self, dest: &Path, contents: &str) -> Result<(), WriteError> {
        let temp = self.write_temp(dest, contents)?;
        let linked = fs::hard_link(&temp, dest);
        let _ = fs::remove_file(&temp);
        linked.map_err(|source| {
            if source.kind() == io::ErrorKind::AlreadyExists {
                WriteError::Exists(dest.to_owned())
            } else {
                WriteError::Io {
                    path: dest.to_owned(),
                    source,
                }
            }
        })
    }
}

impl SourceWriter {
    /// Writes `contents` to `dest`, replacing it in one step if it exists.
    pub fn replace(&self, dest: &Path, contents: &str) -> Result<(), WriteError> {
        let temp = self.write_temp(dest, contents)?;
        fs::rename(&temp, dest).map_err(|source| {
            let _ = fs::remove_file(&temp);
            WriteError::Io {
                path: dest.to_owned(),
                source,
            }
        })
    }

    /// Writes a hidden file beside `dest`, for linking or renaming into place.
    fn write_temp(&self, dest: &Path, contents: &str) -> Result<PathBuf, WriteError> {
        let name = dest
            .file_name()
            .map_or_else(String::new, |n| n.to_string_lossy().into_owned());
        let temp = dest.with_file_name(format!(".{name}.{}.tmp", self.run_id));
        fs::write(&temp, contents).map_err(|source| WriteError::Io {
            path: temp.clone(),
            source,
        })?;
        Ok(temp)
    }
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn creates_once_and_never_replaces() {
        let dir = tempfile::tempdir().unwrap();
        let dest = dir.path().join("issue.toml");
        let writer = SourceWriter::new("run");

        writer.create(&dest, "a = 1\n").unwrap();
        assert_eq!(fs::read_to_string(&dest).unwrap(), "a = 1\n");
        assert!(matches!(
            writer.create(&dest, "a = 2\n"),
            Err(WriteError::Exists(_))
        ));
        assert_eq!(fs::read_to_string(&dest).unwrap(), "a = 1\n");
        // Nothing left behind but the file itself.
        assert_eq!(fs::read_dir(dir.path()).unwrap().count(), 1);
    }

    #[test]
    fn replace_overwrites_in_one_step() {
        let dir = tempfile::tempdir().unwrap();
        let dest = dir.path().join("state.toml");
        let writer = SourceWriter::new("run");
        writer.replace(&dest, "status = 1\n").unwrap();
        writer.replace(&dest, "status = 2\n").unwrap();
        assert_eq!(fs::read_to_string(&dest).unwrap(), "status = 2\n");
        assert_eq!(fs::read_dir(dir.path()).unwrap().count(), 1);
    }

    #[test]
    fn an_unwritable_directory_is_an_error() {
        let writer = SourceWriter::new("run");
        let result = writer.create(Path::new("/nonexistent-dir/issue.toml"), "");
        assert!(matches!(result, Err(WriteError::Io { .. })));
    }
}
