//! What a command can end with, and the exit status each maps to.

use std::process::ExitCode;

use crate::adapters::container::ContainerError;
use crate::config::ConfigError;

/// How a command that ran to the end turned out.
#[derive(Debug, Clone, Copy, PartialEq, Eq)]
pub enum Outcome {
    /// Everything was done or found as expected.
    Clean,
    /// The work was done, but some issues failed or an audit found discrepancies.
    Findings,
    /// The command could not do its job, for example because a required check failed.
    Failed,
}

/// Errors that stop a command before it finishes.
#[derive(Debug, thiserror::Error)]
pub enum Error {
    #[error(transparent)]
    Config(#[from] ConfigError),
    #[error(transparent)]
    Container(#[from] ContainerError),
    #[error("cannot read {path}: {source}")]
    Source {
        path: std::path::PathBuf,
        source: std::io::Error,
    },
    #[error("cannot start the async runtime: {0}")]
    Runtime(std::io::Error),
}

/// Exit statuses. 2 keeps the meaning the old wrapper scripts gave it; 64 is `EX_USAGE`.
pub mod exit {
    pub const CLEAN: u8 = 0;
    pub const FAILED: u8 = 1;
    pub const FINDINGS: u8 = 2;
    pub const USAGE: u8 = 64;
}

impl Outcome {
    #[must_use]
    pub fn exit_code(self) -> ExitCode {
        ExitCode::from(match self {
            Self::Clean => exit::CLEAN,
            Self::Findings => exit::FINDINGS,
            Self::Failed => exit::FAILED,
        })
    }
}

impl Error {
    #[must_use]
    pub fn exit_code(&self) -> ExitCode {
        ExitCode::from(match self {
            Self::Config(_) => exit::USAGE,
            Self::Container(_) | Self::Runtime(_) | Self::Source { .. } => exit::FAILED,
        })
    }
}
