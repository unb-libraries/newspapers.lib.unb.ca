//! SHA-256 of files, computed on tokio's blocking threads so hashing many large images does not
//! stall the async work around it.

use std::fmt::Write as _;
use std::fs::File;
use std::io::{self, Read};
use std::path::PathBuf;

use sha2::{Digest, Sha256};

/// Bytes read per call: large enough for few round trips on NFS.
const BUFFER: usize = 1 << 20;

/// The SHA-256 of a file as lowercase hex, as the site stores it.
pub async fn sha256_file(path: PathBuf) -> io::Result<String> {
    tokio::task::spawn_blocking(move || sha256_blocking(&path))
        .await
        .map_err(io::Error::other)?
}

fn sha256_blocking(path: &std::path::Path) -> io::Result<String> {
    let mut file = File::open(path)?;
    let mut hasher = Sha256::new();
    let mut buffer = vec![0; BUFFER];
    loop {
        let read = file.read(&mut buffer)?;
        if read == 0 {
            break;
        }
        hasher.update(&buffer[..read]);
    }
    let mut hex = String::with_capacity(64);
    for byte in hasher.finalize() {
        // Writing to a String cannot fail.
        let _ = write!(hex, "{byte:02x}");
    }
    Ok(hex)
}

#[cfg(test)]
mod tests {
    use super::*;

    #[tokio::test]
    async fn matches_sha256sum() {
        let dir = tempfile::tempdir().unwrap();
        let path = dir.path().join("page.jpg");
        std::fs::write(&path, b"abc").unwrap();
        assert_eq!(
            sha256_file(path).await.unwrap(),
            "ba7816bf8f01cfea414140de5dae2223b00361a396177a9cb410ff61f20015ad"
        );
    }
}
