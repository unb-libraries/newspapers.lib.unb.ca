//! `convert <path>`: writes an `issue.toml` beside each legacy `metadata.php` that has none.
//!
//! An `issue.toml` holds only what its `title.toml` does not, so issues need a `title.toml` above
//! them first. For issues without one, `convert` proposes the `title.toml` files to create
//! instead of guessing a title ID.

use std::collections::BTreeMap;
use std::path::{Path, PathBuf};

use tracing::info;

use crate::adapters::source_tree::{self, IssueDir};
use crate::adapters::source_write::SourceWriter;
use crate::config::Config;
use crate::domain::metadata::{IssueFields, IssueMetadata, TitleFile, render_issue_toml};
use crate::error::{Error, Outcome};
use crate::source::{Titles, read_legacy};

/// What happened to one issue.
enum Converted {
    Written,
    WouldWrite(String),
    /// No title file above; these are the values one would hold.
    NeedsTitle {
        title: String,
        language: String,
        media: String,
    },
    Failed(String),
}

/// The `title.toml` proposed for the issues of one title that have none: placed in the deepest
/// directory above all of them, with their most common language and media. Issues that differ
/// keep their own values in `issue.toml`.
#[derive(Default)]
struct Proposal {
    dir: Option<PathBuf>,
    issues: usize,
    languages: BTreeMap<String, usize>,
    media: BTreeMap<String, usize>,
}

impl Proposal {
    fn add(&mut self, issue_dir: &Path, language: String, media: String) {
        let parent = issue_dir.parent().unwrap_or(issue_dir);
        self.dir = Some(match &self.dir {
            Some(dir) => common_ancestor(dir, parent),
            None => parent.to_owned(),
        });
        self.issues += 1;
        *self.languages.entry(language).or_default() += 1;
        *self.media.entry(media).or_default() += 1;
    }
}

/// The value most issues have; the first in order on a tie, so output is stable.
fn most_common(counts: &BTreeMap<String, usize>) -> &str {
    counts
        .iter()
        .max_by(|(a_value, a), (b_value, b)| a.cmp(b).then(b_value.cmp(a_value)))
        .map_or("", |(value, _)| value)
}

pub fn run(config: &Config, root: &Path, dry_run: bool, run_id: &str) -> Result<Outcome, Error> {
    info!("scanning {}", root.display());
    let tree = source_tree::scan(root, &config.source).map_err(|source| Error::Source {
        path: root.to_owned(),
        source,
    })?;
    let writer = SourceWriter::new(run_id);
    let mut titles = Titles::default();
    let mut proposals: BTreeMap<String, Proposal> = BTreeMap::new();
    let (mut written, mut already, mut failed) = (0, 0, 0);

    for issue in &tree.issues {
        if issue.metadata_files.has_issue_file() {
            already += 1;
            continue;
        }
        if !issue.metadata_files.has_legacy() {
            continue;
        }
        match convert(issue, config, &mut titles, &writer, dry_run) {
            Converted::Written => {
                written += 1;
                println!(
                    "wrote    {}",
                    issue.path.join(&config.source.issue_file).display()
                );
            }
            Converted::WouldWrite(text) => {
                written += 1;
                println!(
                    "# {}\n{text}",
                    issue.path.join(&config.source.issue_file).display()
                );
            }
            Converted::NeedsTitle {
                title,
                language,
                media,
            } => proposals
                .entry(title)
                .or_default()
                .add(&issue.path, language, media),
            Converted::Failed(error) => {
                failed += 1;
                println!("FAILED   {}\n         {error}", issue.path.display());
            }
        }
    }

    let needing_title: usize = proposals.values().map(|p| p.issues).sum();
    if !proposals.is_empty() {
        println!(
            "\n{needing_title} issues have no {} above them. Create these, set title_id to the \
             Digital Title ID on the site, and run convert again:",
            config.source.title_file
        );
        for (title, proposal) in &proposals {
            let dir = proposal.dir.as_deref().unwrap_or(root);
            println!(
                "\n# {}  ({} {})\ntitle_id = 0\ntitle = {}\nlanguage = {}\nmedia = {}",
                dir.join(&config.source.title_file).display(),
                proposal.issues,
                if proposal.issues == 1 {
                    "issue"
                } else {
                    "issues"
                },
                quoted(title),
                quoted(most_common(&proposal.languages)),
                quoted(most_common(&proposal.media)),
            );
        }
    }

    let verb = if dry_run { "Would write" } else { "Wrote" };
    println!(
        "\n{verb} {written} issue files; {already} issues already had one; {failed} failed; \
         {needing_title} wait for a title file."
    );
    Ok(if failed > 0 || needing_title > 0 {
        Outcome::Findings
    } else {
        Outcome::Clean
    })
}

fn convert(
    issue: &IssueDir,
    config: &Config,
    titles: &mut Titles,
    writer: &SourceWriter,
    dry_run: bool,
) -> Converted {
    let names = &config.source;
    let legacy = match read_legacy(&issue.path.join(&names.legacy_metadata_file)) {
        Ok(legacy) => legacy,
        Err(error) => return Converted::Failed(error),
    };
    let title = match titles.above(&issue.path, names) {
        Ok(Some((_, title))) => title,
        Ok(None) => {
            return match legacy.fields.resolve(None) {
                Ok(metadata) => Converted::NeedsTitle {
                    title: metadata.title,
                    language: metadata.language,
                    media: metadata.media,
                },
                Err(missing) => {
                    Converted::Failed(format!("{}: {missing}", names.legacy_metadata_file))
                }
            };
        }
        Err(error) => return Converted::Failed(format!("title file {error}")),
    };
    let metadata = match legacy.fields.resolve(Some(&title)) {
        Ok(metadata) => metadata,
        Err(missing) => {
            return Converted::Failed(format!("{}: {missing}", names.legacy_metadata_file));
        }
    };

    let mut notes = vec![format!(
        "Converted from {} by nbnp-ingest {}.",
        names.legacy_metadata_file,
        env!("CARGO_PKG_VERSION")
    )];
    if !legacy.warnings.is_empty() {
        notes.push(format!(
            "{} had {}:",
            names.legacy_metadata_file, legacy.date_source
        ));
        notes.extend(legacy.warnings.iter().map(|w| format!("  {w}")));
    }
    let text = render_issue_toml(&metadata, &title, &notes);
    if let Err(error) = reads_back(&text, &title, &metadata) {
        return Converted::Failed(error);
    }
    if dry_run {
        return Converted::WouldWrite(text);
    }
    match writer.create(&issue.path.join(&names.issue_file), &text) {
        Ok(()) => Converted::Written,
        Err(error) => Converted::Failed(error.to_string()),
    }
}

/// Guards against a rendering bug: the file must say exactly what `metadata.php` said.
fn reads_back(text: &str, title: &TitleFile, expected: &IssueMetadata) -> Result<(), String> {
    let read = IssueFields::parse(text)
        .map_err(|e| e.to_string())?
        .resolve(Some(title))
        .map_err(|e| e.to_string())?;
    if &read == expected {
        Ok(())
    } else {
        Err(format!(
            "the rendered issue.toml reads back differently: {}",
            read.differences(expected).join("; ")
        ))
    }
}

fn quoted(value: &str) -> String {
    toml::Value::String(value.to_owned()).to_string()
}

fn common_ancestor(a: &Path, b: &Path) -> PathBuf {
    a.components()
        .zip(b.components())
        .take_while(|(x, y)| x == y)
        .map(|(x, _)| x)
        .collect()
}

#[cfg(test)]
mod tests {
    use super::*;
    use std::fs;

    const LEGACY: &str = "define('ISSUE_DATE', mktime(0, 0, 0, 31, 4, 1909));
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

    fn config() -> Config {
        Config::parse(include_str!("../../config.example.toml")).unwrap()
    }

    #[test]
    fn proposals_take_the_most_common_values() {
        let mut proposal = Proposal::default();
        proposal.add(Path::new("/m/TN/1909/a"), "eng".into(), "print".into());
        proposal.add(Path::new("/m/TN/1910/b"), "fre".into(), "print".into());
        proposal.add(Path::new("/m/TN/1910/c"), "fre".into(), "print".into());
        assert_eq!(proposal.dir.as_deref(), Some(Path::new("/m/TN")));
        assert_eq!(most_common(&proposal.languages), "fre");

        let tie = BTreeMap::from([("eng".to_owned(), 1), ("fre".to_owned(), 1)]);
        assert_eq!(most_common(&tie), "eng");
    }

    #[test]
    fn common_ancestor_of_two_paths() {
        assert_eq!(
            common_ancestor(Path::new("/m/TN/1909/a"), Path::new("/m/TN/1910/b")),
            PathBuf::from("/m/TN")
        );
    }

    #[test]
    fn writes_issue_toml_under_a_title_and_proposes_titles_elsewhere() {
        let dir = tempfile::tempdir().unwrap();
        let root = dir.path();
        for issue in ["TN/1909/A", "TN/1909/B", "OTHER/1910/C"] {
            fs::create_dir_all(root.join(issue)).unwrap();
            fs::write(root.join(issue).join("metadata.php"), LEGACY).unwrap();
        }
        fs::write(
            root.join("TN/title.toml"),
            "title_id = 48\ntitle = \"The News\"\nlanguage = \"eng\"\nmedia = \"print\"\n",
        )
        .unwrap();

        let outcome = run(&config(), root, false, "test").unwrap();
        assert_eq!(outcome, Outcome::Findings, "OTHER has no title.toml");

        let written = fs::read_to_string(root.join("TN/1909/A/issue.toml")).unwrap();
        assert_eq!(
            written,
            format!(
                "# Converted from metadata.php by nbnp-ingest {}.\n",
                env!("CARGO_PKG_VERSION")
            ) + "# metadata.php had mktime(0, 0, 0, 31, 4, 1909):\n\
             #   line 1: mktime(0, 0, 0, 31, 4, 1909) is not a calendar date at midnight; PHP \
             read it as 1909-05-01\n\
             date = 1909-05-01\n\
             volume = \"33\"\n\
             issue = \"5\"\n"
        );
        assert!(root.join("TN/1909/B/issue.toml").exists());
        assert!(!root.join("OTHER/1910/C/issue.toml").exists());

        // A second run changes nothing it already wrote.
        let again = run(&config(), root, false, "test").unwrap();
        assert_eq!(again, Outcome::Findings);
        assert_eq!(
            fs::read_to_string(root.join("TN/1909/A/issue.toml")).unwrap(),
            written
        );
    }
}
