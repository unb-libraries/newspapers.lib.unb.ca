# nbnp-ingest

Use `nbnp-ingest` to import digitized newspaper issues from the HAMMER source tree into
newspapers.lib.unb.ca, and to check and audit them. It replaces the
`newspapers.lib.unb.ca:create-issues-tree` and `:audit-tree` commands in systems-toolkit, and
the three HAMMER scripts that wrapped them.

To import issues on HAMMER, follow
[Bulk issue operations](../../docs/howto/bulk-issue-operations.md). This README is the
reference for the tool itself.

## Commands

Every command except `config` takes a path: a title, year or issue directory under the source
tree, or any directory above them. The tool finds every *issue directory* below it, meaning
every directory that holds an `issue.toml` or a legacy `metadata.php`.

| Command | Does | Changes anything |
|---|---|---|
| `check <PATH>` | Reads every issue's metadata and page images and reports what would stop an import. Does not contact the site. | No |
| `audit <PATH>` | Compares every issue with the site: metadata, every page by image checksum, tiles and PDFs. | No |
| `import <PATH>` | Shows what an import would do for each issue. With `--yes`, does it. | With `--yes` |
| `run <PATH>` | With `--yes`: `check`, then `import`, then `audit`, in one log and one ledger. Without it: `check`, then the import plan, with no audit and no log. | With `--yes` |
| `convert <PATH>` | Writes an `issue.toml` beside each `metadata.php` that has none. | Yes, unless `--dry-run` |
| `config show` | Prints the configuration with every default filled in. | No |
| `config check` | Tests the host: config, site login, paths, Docker, images, Tesseract languages, write access to the webtree (the site's public files, `paths.public_files`). | Nothing on the site. Creates missing work, log and staging directories and pulls missing images. |

The three HAMMER scripts map to these commands; `run --yes` does all three in one invocation:

| Script | Replaced by |
|---|---|
| `auditIssueMetadata.sh` | `check` |
| `importNbnpIssue.sh` | `run --yes` |
| `auditNbnpIssue.sh` | `audit` |

### Options

| Option | Commands | Effect |
|---|---|---|
| `--config <FILE>` | all | Config file to use. See [Configuration](#configuration). |
| `-v`, `-vv` | all | More detail in the log: debug, then trace. |
| `-V`, `--version` | none | Print the version, and the commit for a CI build. |
| `--title <ID>` | `audit`, `import`, `run` | Digital Title ID to use instead of each issue's `title_id`, for example on a test site. |
| `--dry-run` | `import`, `convert` | Print what would be done and change nothing. |
| `--yes` | `import`, `run` | Carry out the plan. Without it, the plan is printed and nothing is changed. |
| `--force` | `import`, `run` | Import again an issue whose state file says complete but which the site no longer has. |
| `--no-notify` | `import`, `run` | Send no SNS notification when done. |
| `--deep` | `audit` | Also checksum every stored image in the webtree, to find damaged files. |
| `--all` | `check`, `audit`, `import` | List every issue, not only those with something to report. |

### Exit status

| Status | Meaning |
|---|---|
| 0 | Clean: nothing to report. |
| 1 | The command could not do its job, for example the site could not be reached, or a run was stopped with Ctrl-C. |
| 2 | Finished, with findings: issues with errors, blocked or failed issues, or audit differences. |
| 64 | Bad usage or configuration. |

`run` exits with the import's status when the import was not clean, and otherwise with the
audit's.

## Configuration

The tool reads one TOML file, the first it finds of:

1. The file given with `--config`.
2. `$NBNP_INGEST_CONFIG`.
3. `$XDG_CONFIG_HOME/nbnp-ingest/config.toml`, by default `~/.config/nbnp-ingest/config.toml`.

Unknown keys are an error, so a typo stops the tool instead of being ignored.

| File | Holds |
|---|---|
| [`config.example.toml`](config.example.toml) | Every key, with its default and what it does. A test keeps it in step with the code. |
| [`config/hammer.toml`](config/hammer.toml) | The configuration for HAMMER, with both images pinned by digest. A test checks that it is valid. |

Two things are deliberately not in the config file:

- **The site password.** `site.credentials_file` names a TOML file with `username` and
  `password`. Group and others must have no access to it (mode 600 does this), or the tool
  refuses it.
- **AWS credentials** for notifications. They come from the standard AWS chain: the
  environment, `~/.aws/credentials` or a profile.

Run `config check` after any change on a host. It tests what an import needs, including that
a container running as the web server's user can write where tiles and PDFs go.

## What an import does

`import` first plans every issue, the same way `--dry-run` shows it. Each issue is:

- Created, when the site has no issue for it.
- Added to, when the site has part of it.
- Linked (given a state file), when the PHP importer created it.
- Left alone, when it is complete.
- Blocked, with nothing done to it, when the source has errors or the site has something the
  source cannot explain, such as two matching issues or a page with no source image.

For each issue it then carries out, in order:

1. For an issue the site does not have, writes the *state file* `.nbnp_issue.toml` in the
   issue directory with the UUID it chose for the issue, marked pending.
2. Creates that issue on the site, with that UUID.
3. For each missing page: runs Tesseract once for the hOCR, the text and the PDF, uploads the
   image, and creates the page with a UUID derived from the issue's UUID and the page's path.
4. Checks that the site stored each new image with the source image's checksum.
5. Tiles each page that lacks tiles, in memory, and installs its tiles and PDF in the webtree.
6. Waits until the site sees every page's tiles and PDF, then marks the state file complete.

This order is what makes an import safe to run again at any point:

- The UUIDs are chosen before anything is created, and the site refuses a second issue with a
  UUID it already has. After any error or timeout on a create, the tool looks the UUID up
  before deciding whether the create happened, so it never creates an issue or a page twice.
- A run that stops partway leaves the state file pending. The next run finds the issue by
  that UUID and does only what is missing. OCR results are cached in the work directory, so
  pages are not OCR'd again.
- Ctrl-C starts nothing new, removes the run's containers, and prints what finished. Running
  the same command again resumes.
- A state file that cannot be read stops that issue with an error. It is never treated as
  absent.

## Files it writes

| What | Where | When |
|---|---|---|
| State file | `.nbnp_issue.toml` in each issue directory | Pending before the issue is created; complete once the site confirms it. |
| `issue.toml` | Each issue directory | By `convert` only. |
| Tiles | `serials/dzi/<TITLE_ID>/<SITE_ISSUE_ID>/` under `paths.public_files` | For each page without tiles. |
| Page PDFs | `serials/pdf/<TITLE_ID>/<SITE_ISSUE_ID>/` under `paths.public_files` | For each page without a PDF. |
| Staging | `webtree.staging_dir`, by default `serials/.nbnp-ingest-staging` | While a page's tiles and PDF are copied in; removed after. |
| OCR cache | `ocr/` under `paths.work_dir` | Per page until its issue is complete. |
| Log | `<UTC time>_<command>_<directory>.log` in `paths.log_dir` | `import --yes` and `run --yes`: everything printed and logged. |
| Ledger | `<UTC time>_<command>_<directory>.jsonl` in `paths.log_dir` | With the log. One JSON line per issue, with its outcome, site issue ID, pages uploaded and time. |

A container running as `webtree.owner_uid:owner_gid`, the web server's user, writes the tiles
and PDFs. It tiles each page in memory (a tmpfs of up to 2 GiB, about 20 MB used for a typical
page) and copies the result into staging: `vips dzsave` writing straight to the webtree's NFS
renames, chmods and chowns every one of a page's thousand or so tiles, which slows the NFS
server the site also uses. With `webtree.directory_group` set, the tool creates the title and issue
directories itself, mode 775, and the container joins that group to write into them.

When the config has a `[notify]` section, a run with `--yes` also publishes its summary to
`summary_topic_arn` and a one-line result to `event_topic_arn`, as the PHP importer did. A run
that created, added to and failed nothing (every issue already complete, or skipped) sends
nothing.

A run's summary looks like this (from a test import on a local site):

```text
[audit-src/BTG/1850/BTG_1850_1_12] Import finished
Run time: 0:00:05
Issues: 1 (1 attempted)
Created: 1
Added to: 0
Nothing to do: 0
Blocked: 0
Failed: 0
Pages uploaded: 2
Average per attempted issue: 0:00:05
```

## Development

The tool is a Rust crate. CI uses Rust 1.95.0, and clippy's lints change between Rust
versions, so install that version with clippy and rustfmt, and make it the default in
`tools/nbnp-ingest`. From `tools/nbnp-ingest`:

```bash
rustup toolchain install 1.95.0 --component clippy,rustfmt
rustup override set 1.95.0
```

Then run the same checks as CI, from the same directory:

```bash
cargo fmt --check
cargo clippy --all-targets --locked -- -D warnings
cargo test --locked
```

To try an import against a local copy of the site, point a config at it: `site.base_url` at
the local site, `paths.public_files` at a directory the site serves its files from, and
`--title` at a local title ID.

HAMMER runs a static binary. Building one needs `musl-tools`, because the ring dependency
compiles some C. With it installed:

```bash
rustup target add x86_64-unknown-linux-musl
cargo build --release --locked --target x86_64-unknown-linux-musl
```

The binary is `target/x86_64-unknown-linux-musl/release/nbnp-ingest`. Set
`NBNP_INGEST_COMMIT` when building to have `--version` show the commit.

The vips image used for tiling is built from [`docker/vips/Dockerfile`](docker/vips/Dockerfile).
CI publishes it to `ghcr.io/unb-libraries/nbnp-ingest-vips` when the Dockerfile changes on
`dev`, or on a manual run of the workflow on `dev`.

## Releasing

1. On `dev`, set the new version in `Cargo.toml`, run `cargo build` to update `Cargo.lock`,
   commit both, and push `dev`.
2. Tag that commit `nbnp-ingest-v<VERSION>`, where `<VERSION>` matches `Cargo.toml`, and push
   the tag.

   CI checks that the tag matches the version, builds the static binary, and publishes a
   GitHub release with `nbnp-ingest-x86_64-linux` and its `.sha256`.

3. Confirm that the workflow's `release` job passed and that the GitHub release
   `nbnp-ingest-v<VERSION>` has `nbnp-ingest-x86_64-linux` and its `.sha256`.
