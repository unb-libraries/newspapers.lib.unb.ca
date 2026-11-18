//! Installs a page's tiles (DZI) and PDF into the webtree, as the web server's user.
//!
//! One container per page tiles the source image with `vips dzsave` into a staging directory in
//! the webtree, then renames everything into place: the tile directory, the PDF, and the `.dzi`
//! file last. The site counts tiles only when the `.dzi` exists, so a page is never shown with
//! half its tiles, and a failed page leaves only its staging directory behind.

use std::path::{Path, PathBuf};
use std::sync::atomic::{AtomicU64, Ordering};
use std::time::Duration;

use super::container::{ContainerError, Mount, Runtime, Spec};
use crate::config::Config;

#[derive(Debug, thiserror::Error)]
pub enum AssetError {
    #[error(transparent)]
    Container(#[from] ContainerError),
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

/// Tiles and copies as the webtree owner; values reach the script as positional parameters.
const SCRIPT: &str = r#"set -eu
image=$1 base=$2 stage=$3 dzi_dir=$4 pdf_dir=$5 tile=$6 overlap=$7 quality=$8 pdf=$9
mkdir -p "$stage"
if [ -n "$dzi_dir" ]; then
  vips dzsave "$image" "$stage/$base" --tile-size "$tile" --overlap "$overlap" \
    --depth onepixel --suffix ".jpg[Q=$quality,strip]"
  rm -f "$stage/${base}_files/vips-properties.xml"
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
rmdir "$stage"
"#;

#[derive(Debug)]
pub struct Installer {
    image: String,
    owner: (u32, u32),
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
        ];
        Ok(Spec {
            image: self.image.clone(),
            user: Some(self.owner),
            mounts,
            // One thread per page: `limits.cpu_slots` pages tile at once.
            env: vec![("VIPS_CONCURRENCY".into(), "1".into())],
            entrypoint: Some("sh".into()),
            args: args.into_iter().map(Into::into).collect(),
            timeout: self.timeout,
        })
    }
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
            ]
        );
        assert_eq!(spec.user, Some((100, 102)));
        assert_eq!(spec.mounts.len(), 3);
    }
}
