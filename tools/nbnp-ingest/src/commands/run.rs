//! `run <path>`: the regular ingest in one command, replacing HAMMER's three scripts: `check`,
//! then `import` (which changes nothing without `--yes`), then `audit` of the same path, all in
//! one log and one ledger.
//!
//! The audit runs even after a failed import, to show what is on the site. The exit status is
//! the import's when it did not finish cleanly, otherwise the audit's, as the scripts had it.

use std::path::Path;

use crate::config::Config;
use crate::error::{Error, Outcome};
use crate::report::outln;

use super::{audit, check, import};

#[derive(Debug, Clone, Copy, Default)]
pub struct Options {
    pub title: Option<u32>,
    pub yes: bool,
    pub force: bool,
    pub notify: bool,
}

pub async fn run(
    config: &Config,
    root: &Path,
    options: Options,
    run_id: &str,
) -> Result<Outcome, Error> {
    let log = options
        .yes
        .then(|| import::open_log(config, "run", root))
        .transpose()?;

    outln!("== Checking the source\n");
    // Issues with errors are reported here and blocked by the import, which goes on with the
    // rest, so the check's own outcome does not stop the run.
    check::run(config, root, false)?;

    outln!("\n== Importing\n");
    let imported = import::run(
        config,
        root,
        import::Options {
            title: options.title,
            force: options.force,
            all: false,
            mode: if options.yes {
                import::Mode::Run
            } else {
                import::Mode::ShowOnly
            },
            notify: options.notify,
        },
        run_id,
        log.as_ref(),
    )
    .await?;
    if !options.yes {
        return Ok(imported);
    }

    outln!("\n== Auditing\n");
    let audited = audit::run(
        config,
        root,
        audit::Options {
            title: options.title,
            deep: false,
            all: false,
        },
    )
    .await?;

    Ok(if imported == Outcome::Clean {
        audited
    } else {
        imported
    })
}
