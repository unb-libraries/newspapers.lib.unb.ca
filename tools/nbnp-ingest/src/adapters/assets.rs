//! Installs a page's tiles (DZI) and PDF into the webtree, as the web server's user.
//!
//! One container per page tiles the source image with `vips dzsave` in memory, copies the tiles
//! and PDF into a staging directory in the webtree, then renames everything into place: the tile
//! directory, the PDF, and the `.dzi` file last. The site counts tiles only when the `.dzi`
//! exists, so a page is never shown with half its tiles, and a failed page removes its staging
//! directory.
//!
//! The webtree is on NFS, shared with the site's pods. `dzsave` writes each of a page's thousand
//! or so tiles to a temporary file, then renames it and sets its mode and owner: done on NFS,
//! that is several metadata operations a tile, enough to slow the NFS server for the site. Tiled
//! in memory and then copied, each tile costs one create, one write and one close.
//!
//! With `webtree.directory_group` set, the title and issue directories are created here, by the
//! account running the tool, mode 775, and the container joins that group to write into them:
//! the layout the PHP importer left, with directories owned by that account and files by the
//! web server's user.

use std::path::{Path, PathBuf};
use std::sync::atomic::{AtomicU64, Ordering};
use std::time::Duration;

use super::container::{ContainerError, Mount, Runtime, Spec, Tmpfs};
use crate::config::Config;

#[derive(Debug, thiserror::Error)]
pub enum AssetError {
    #[error(transparent)]
    Container(#[from] ContainerError),
    #[error("cannot create {path}: {source}")]
    Directory {
        path: PathBuf,
        source: std::io::Error,
    },
    #[error("asset name {0:?} is not a plain file name")]
    BadName(String),
}

/// What to install for one page.
#[derive(Debug, Clone)]
pub struct PageAssets<'a> {
    /// The source image, tiled when `dzi_dir` is set.
    pub image: &'a Path,
    /// The stored image's name without extension; tiles and PDF are named after it.
    pub basename: &'a str,
    /// Relative to the public files directory; `None` installs no tiles.
    pub dzi_dir: Option<String>,
    /// The PDF to install, and where, relative to the public files directory.
    pub pdf: Option<(&'a Path, String)>,
}

/// Where the container tiles, in memory.
const SCRATCH: &str = "/scratch";

/// The cap on a page's tiles in memory. A 13 MB scan makes 18 MB of tiles; the cap only stops a
/// runaway page, since memory is used as tiles are written.
const SCRATCH_MIB: u64 = 2048;

/// Tiles and copies as the webtree owner; values reach the script as positional parameters.
const SCRIPT: &str = r#"set -eu
image=$1 base=$2 stage=$3 dzi_dir=$4 pdf_dir=$5 tile=$6 overlap=$7 quality=$8 pdf=$9 scratch=${10}
trap 'rm -rf "$stage"' EXIT
if [ -n "$dzi_dir" ]; then
  vips dzsave "$image" "$scratch/$base" --tile-size "$tile" --overlap "$overlap" \
    --depth onepixel --suffix ".jpg[Q=$quality,strip]"
  rm -f "$scratch/${base}_files/vips-properties.xml"
fi
mkdir -p "$stage"
if [ -n "$dzi_dir" ]; then
  cp -R "$scratch/${base}_files" "$stage/${base}_files"
  cp "$scratch/$base.dzi" "$stage/$base.dzi"
fi
if [ -n "$pdf_dir" ]; then cp "$pdf" "$stage/$base.pdf"; fi
if [ -n "$dzi_dir" ]; then
  mkdir -p "$dzi_dir"
  rm -f "$dzi_dir/$base.dzi"
  rm -rf "$dzi_dir/${base}_files"
  mv "$stage/${base}_files" "$dzi_dir/${base}_files"
fi
if [ -n "$pdf_dir" ]; then
  mkdir -p "$pdf_dir"
  mv "$stage/$base.pdf" "$pdf_dir/$base.pdf"
fi
if [ -n "$dzi_dir" ]; then mv "$stage/$base.dzi" "$dzi_dir/$base.dzi"; fi
"#;

#[derive(Debug)]
pub struct Installer {
    image: String,
    owner: (u32, u32),
    directory_group: Option<u32>,
    public_files: PathBuf,
    staging: String,
    tile_size: u32,
    overlap: u32,
    jpeg_quality: u8,
    timeout: Duration,
    run_id: String,
    next: AtomicU64,
}

impl Installer {
    #[must_use]
    pub fn new(config: &Config, run_id: &str) -> Self {
        Self {
            image: config.dzi.image.clone(),
            owner: (config.webtree.owner_uid, config.webtree.owner_gid),
            directory_group: config.webtree.directory_group,
            public_files: config.paths.public_files.clone(),
            staging: config.webtree.staging_dir.to_string_lossy().into_owned(),
            tile_size: config.dzi.tile_size,
            overlap: config.dzi.overlap,
            jpeg_quality: config.dzi.jpeg_quality,
            timeout: Duration::from_secs(config.dzi.timeout_secs),
            run_id: run_id.to_owned(),
            next: AtomicU64::new(1),
        }
    }

    pub async fn install(
        &self,
        runtime: &Runtime,
        page: &PageAssets<'_>,
    ) -> Result<(), AssetError> {
        let spec = self.spec(page)?;
        if self.directory_group.is_some() {
            let dirs = [
                page.dzi_dir.as_deref(),
                page.pdf.as_ref().map(|(_, dir)| dir.as_str()),
            ];
            for dir in dirs.into_iter().flatten() {
                create_group_writable(&self.public_files, dir)?;
            }
        }
        runtime.run(&spec).await?;
        Ok(())
    }

    fn spec(&self, page: &PageAssets<'_>) -> Result<Spec, AssetError> {
        if !is_plain_name(page.basename) {
            return Err(AssetError::BadName(page.basename.to_owned()));
        }
        let image_name = page
            .image
            .file_name()
            .map(|n| n.to_string_lossy().into_owned())
            .unwrap_or_default();
        let stage = format!(
            "/files/{}/{}-{}",
            self.staging,
            self.run_id,
            self.next.fetch_add(1, Ordering::Relaxed)
        );
        let in_files = |relative: &Option<String>| {
            relative
                .as_ref()
                .map_or_else(String::new, |r| format!("/files/{r}"))
        };
        let pdf_dir = page.pdf.as_ref().map(|(_, dir)| dir.clone());
        let pdf_in_container = page.pdf.as_ref().map_or_else(String::new, |(pdf, _)| {
            format!(
                "/pdf/{}",
                pdf.file_name()
                    .map(|n| n.to_string_lossy().into_owned())
                    .unwrap_or_default()
            )
        });

        let mut mounts = vec![
            Mount {
                host: page.image.parent().unwrap_or(Path::new("/")).to_owned(),
                target: "/in".into(),
                read_only: true,
            },
            Mount {
                host: self.public_files.clone(),
                target: "/files".into(),
                read_only: false,
            },
        ];
        if let Some((pdf, _)) = &page.pdf {
            mounts.push(Mount {
                host: pdf.parent().unwrap_or(Path::new("/")).to_owned(),
                target: "/pdf".into(),
                read_only: true,
            });
        }
        let args = [
            "-c".to_owned(),
            SCRIPT.to_owned(),
            "sh".to_owned(),
            format!("/in/{image_name}"),
            page.basename.to_owned(),
            stage,
            in_files(&page.dzi_dir),
            in_files(&pdf_dir),
            self.tile_size.to_string(),
            self.overlap.to_string(),
            self.jpeg_quality.to_string(),
            pdf_in_container,
            SCRATCH.to_owned(),
        ];
        Ok(Spec {
            image: self.image.clone(),
            user: Some(self.owner),
            groups: self.directory_group.into_iter().collect(),
            mounts,
            tmpfs: vec![Tmpfs {
                target: SCRATCH.into(),
                size_mib: SCRATCH_MIB,
            }],
            // One thread per page: `limits.cpu_slots` pages tile at once.
            env: vec![("VIPS_CONCURRENCY".into(), "1".into())],
            entrypoint: Some("sh".into()),
            args: args.into_iter().map(Into::into).collect(),
            timeout: self.timeout,
        })
    }
}

/// Creates each missing directory of `relative` under `root`, mode 775 whatever the umask, so
/// the directory group can write into it.
pub fn create_group_writable(root: &Path, relative: &str) -> Result<(), AssetError> {
    use std::os::unix::fs::PermissionsExt;

    let mut path = root.to_owned();
    for part in relative.split('/').filter(|p| !p.is_empty()) {
        path.push(part);
        if path.is_dir() {
            continue;
        }
        let made = std::fs::create_dir(&path)
            .and_then(|()| std::fs::set_permissions(&path, std::fs::Permissions::from_mode(0o775)));
        // Another page may have created it first.
        if let Err(source) = made
            && !path.is_dir()
        {
            return Err(AssetError::Directory { path, source });
        }
    }
    Ok(())
}

/// A file name with no directory part: letters, digits, `.`, `_` and `-`, not starting with `.`.
fn is_plain_name(name: &str) -> bool {
    !name.is_empty()
        && !name.starts_with('.')
        && name
            .chars()
            .all(|c| c.is_ascii_alphanumeric() || matches!(c, '.' | '_' | '-'))
}

#[cfg(test)]
mod tests {
    use super::*;

    fn installer() -> Installer {
        let config = Config::parse(include_str!("../../config.example.toml")).unwrap();
        Installer::new(&config, "run")
    }

    #[test]
    fn names_with_paths_are_refused() {
        for bad in ["../x", "a/b", ".hidden", "", "a b"] {
            assert!(!is_plain_name(bad), "{bad:?}");
        }
        assert!(is_plain_name("53317-0001"));
        let page = PageAssets {
            image: Path::new("/src/a.jpg"),
            basename: "../../etc",
            dzi_dir: Some("serials/dzi/1/2".into()),
            pdf: None,
        };
        assert!(matches!(
            installer().spec(&page),
            Err(AssetError::BadName(_))
        ));
    }

    #[test]
    fn directories_are_created_group_writable() {
        use std::os::unix::fs::PermissionsExt;

        let root = tempfile::tempdir().unwrap();
        create_group_writable(root.path(), "serials/dzi/248/53319").unwrap();
        create_group_writable(root.path(), "serials/dzi/248/53319").unwrap();
        for dir in ["serials", "serials/dzi/248", "serials/dzi/248/53319"] {
            let mode = std::fs::metadata(root.path().join(dir))
                .unwrap()
                .permissions()
                .mode();
            assert_eq!(mode & 0o777, 0o775, "{dir}");
        }
    }

    #[test]
    fn values_are_positional_parameters() {
        let page = PageAssets {
            image: Path::new("/mnt/nbnp/T/I/001_T_1909_1_1_01.jpg"),
            basename: "53317-0001",
            dzi_dir: Some("serials/dzi/248/53317".into()),
            pdf: Some((
                Path::new("/work/ocr/x/page.pdf"),
                "serials/pdf/248/53317".into(),
            )),
        };
        let spec = installer().spec(&page).unwrap();
        let args: Vec<String> = spec
            .args
            .iter()
            .map(|a| a.to_string_lossy().into_owned())
            .collect();
        assert_eq!(args[0], "-c");
        assert_eq!(
            args[3..],
            [
                "/in/001_T_1909_1_1_01.jpg",
                "53317-0001",
                "/files/serials/.nbnp-ingest-staging/run-1",
                "/files/serials/dzi/248/53317",
                "/files/serials/pdf/248/53317",
                "256",
                "0",
                "80",
                "/pdf/page.pdf",
                "/scratch",
            ]
        );
        assert_eq!(spec.tmpfs.len(), 1);
        assert_eq!(spec.tmpfs[0].target, "/scratch");
        assert_eq!(spec.user, Some((100, 102)));
        assert_eq!(spec.groups, [2100]);
        assert_eq!(spec.mounts.len(), 3);
    }
}
