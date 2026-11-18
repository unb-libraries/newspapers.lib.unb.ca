//! Command-line arguments. Everything that is not specific to one run belongs in the config file.

use std::path::PathBuf;
use std::sync::OnceLock;

use clap::{CommandFactory, FromArgMatches, Parser, Subcommand};

#[derive(Debug, Parser)]
#[command(name = "nbnp-ingest", version, about)]
pub struct Cli {
    /// Config file [default: `$NBNP_INGEST_CONFIG`, then ~/.config/nbnp-ingest/config.toml]
    #[arg(long, global = true, value_name = "FILE")]
    pub config: Option<PathBuf>,

    /// More detail in the log: -v for debug, -vv for trace
    #[arg(short, long, global = true, action = clap::ArgAction::Count)]
    pub verbose: u8,

    #[command(subcommand)]
    pub command: Command,
}

#[derive(Debug, Subcommand)]
pub enum Command {
    /// Check issue metadata and page images under a path, without contacting the site
    Check {
        /// A title, year or issue directory, or anything above them
        path: PathBuf,
        /// List every issue, not only those with findings
        #[arg(long)]
        all: bool,
    },
    /// Compare every issue under a path with the site; reads only
    Audit {
        /// A title, year or issue directory, or anything above them
        path: PathBuf,
        /// Digital Title ID to use instead of each issue's `title_id`, e.g. on a test site
        #[arg(long, value_name = "ID")]
        title: Option<u32>,
        /// Also checksum every stored image, to find damaged files
        #[arg(long)]
        deep: bool,
        /// List every issue, not only those with findings
        #[arg(long)]
        all: bool,
    },
    /// Write an issue.toml beside each legacy metadata.php that has none
    Convert {
        /// A title, year or issue directory, or anything above them
        path: PathBuf,
        /// Print the files instead of writing them
        #[arg(long)]
        dry_run: bool,
    },
    /// Inspect the configuration
    #[command(subcommand)]
    Config(ConfigCommand),
}

#[derive(Debug, Subcommand)]
pub enum ConfigCommand {
    /// Print the configuration with every default filled in
    Show,
    /// Check that this host can run imports: paths, credentials, container runtime and images
    Check,
}

/// The version, with the commit the release was built from when CI provides it.
fn long_version() -> &'static str {
    static VERSION: OnceLock<String> = OnceLock::new();
    VERSION.get_or_init(|| {
        format!(
            "{} (commit {})",
            env!("CARGO_PKG_VERSION"),
            option_env!("NBNP_INGEST_COMMIT").unwrap_or("unknown")
        )
    })
}

impl Cli {
    pub fn try_parse_args() -> Result<Self, clap::Error> {
        let matches = Self::command()
            .long_version(long_version())
            .try_get_matches()?;
        Self::from_arg_matches(&matches)
    }
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn definition_is_consistent() {
        Cli::command().debug_assert();
    }

    #[test]
    fn global_options_follow_the_subcommand() {
        let cli = Cli::try_parse_from([
            "nbnp-ingest",
            "config",
            "check",
            "--config",
            "/c.toml",
            "-vv",
        ])
        .unwrap();
        assert_eq!(cli.config, Some(PathBuf::from("/c.toml")));
        assert_eq!(cli.verbose, 2);
        assert!(matches!(cli.command, Command::Config(ConfigCommand::Check)));
    }
}
