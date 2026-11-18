//! nbnp-ingest: imports and audits digitized newspaper issues for newspapers.lib.unb.ca.
//!
//! `main.rs` only calls [`main`]. Commands live in `commands`, the rules they apply in `domain`,
//! and everything that touches the outside world in `adapters`.

mod adapters;
mod cli;
mod commands;
mod config;
mod domain;
mod error;
mod ingest;
mod report;
mod source;

use std::process::ExitCode;
use std::time::{SystemTime, UNIX_EPOCH};

use tracing::{Level, error};
use tracing_subscriber::filter::LevelFilter;
use tracing_subscriber::fmt::layer;
use tracing_subscriber::layer::SubscriberExt as _;
use tracing_subscriber::util::SubscriberInitExt as _;

use cli::{Cli, Command, ConfigCommand};
use config::Config;
use error::{Error, Outcome, exit};

/// Parses arguments, runs the command and returns the process exit status.
#[must_use]
pub fn main() -> ExitCode {
    let cli = match Cli::try_parse_args() {
        Ok(cli) => cli,
        Err(error) => {
            // clap exits 2 on usage errors, which here means "finished with findings".
            let _ = error.print();
            return if error.use_stderr() {
                ExitCode::from(exit::USAGE)
            } else {
                ExitCode::SUCCESS
            };
        }
    };
    init_logging(cli.verbose);

    match run(&cli) {
        Ok(outcome) => outcome.exit_code(),
        Err(err) => {
            error!("{err}");
            err.exit_code()
        }
    }
}

fn run(cli: &Cli) -> Result<Outcome, Error> {
    let loaded = Config::load(cli.config.as_deref())?;
    match &cli.command {
        Command::Audit {
            path,
            title,
            deep,
            all,
        } => async_runtime()?.block_on(commands::audit::run(
            &loaded.config,
            path,
            commands::audit::Options {
                title: *title,
                deep: *deep,
                all: *all,
            },
        )),
        Command::Import {
            path,
            title,
            dry_run,
            yes,
            force,
            all,
            no_notify,
        } => async_runtime()?.block_on(commands::import::run(
            &loaded.config,
            path,
            commands::import::Options {
                title: *title,
                force: *force,
                all: *all,
                mode: match (*dry_run, *yes) {
                    (true, _) => commands::import::Mode::DryRun,
                    (false, true) => commands::import::Mode::Run,
                    (false, false) => commands::import::Mode::ShowOnly,
                },
                notify: !*no_notify,
            },
            &run_id(),
            None,
        )),
        Command::Run {
            path,
            title,
            yes,
            force,
            no_notify,
        } => async_runtime()?.block_on(commands::run::run(
            &loaded.config,
            path,
            commands::run::Options {
                title: *title,
                yes: *yes,
                force: *force,
                notify: !*no_notify,
            },
            &run_id(),
        )),
        Command::Check { path, all } => commands::check::run(&loaded.config, path, *all),
        Command::Convert { path, dry_run } => {
            commands::convert::run(&loaded.config, path, *dry_run, &run_id())
        }
        Command::Config(ConfigCommand::Show) => Ok(commands::config::show(&loaded)),
        Command::Config(ConfigCommand::Check) => {
            async_runtime()?.block_on(commands::config::check(&loaded, &run_id()))
        }
    }
}

fn async_runtime() -> Result<tokio::runtime::Runtime, Error> {
    tokio::runtime::Builder::new_multi_thread()
        .enable_all()
        .build()
        .map_err(Error::Runtime)
}

/// Identifies this run in container labels and probe file names.
fn run_id() -> String {
    let secs = SystemTime::now()
        .duration_since(UNIX_EPOCH)
        .map_or(0, |d| d.as_secs());
    format!("{secs}-{}", std::process::id())
}

fn init_logging(verbose: u8) {
    let level = match verbose {
        0 => Level::INFO,
        1 => Level::DEBUG,
        _ => Level::TRACE,
    };
    // The terminal, and a copy without colour codes for the run's log file once it is open.
    tracing_subscriber::registry()
        .with(LevelFilter::from_level(level))
        .with(layer().with_writer(std::io::stderr).with_target(false))
        .with(
            layer()
                .with_writer(|| adapters::run_log::LogFileWriter)
                .with_ansi(false)
                .with_target(false),
        )
        .init();
}
