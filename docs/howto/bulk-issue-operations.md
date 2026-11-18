---
title: Import and audit issues in bulk
status: active
owner: jsanford@unb.ca
last_reviewed: 2026-11-18
last_reviewed_by: jsanford@unb.ca
description: Import NBNP newspaper issues in bulk from HAMMER, and audit them against the site.
---

# Import and audit issues in bulk

Use this guide to put a directory of scanned issues on newspapers.lib.unb.ca, with page
images, OCR, viewer tiles and PDFs, and to check that the site matches the scans. The
scripts on HAMMER run the nbnp-ingest tool; its
[README](../../tools/nbnp-ingest/README.md) describes the tool itself.

## Before you start

| Prerequisite | Check |
|---|---|
| SSH access to HAMMER as the `imaging` user | `ssh imaging@hammer` logs you in. |
| The directory of issues to import | `ls <PATH>` lists issue directories. Import one year at a time rather than thousands of issues at once. |
| The Digital Title ID the issues belong to | See `<TITLE_ID>` below. |

| Placeholder | Meaning | How to obtain it |
|---|---|---|
| `<PATH>` | The directory to import, with a trailing slash | For example `/mnt/nbnp/TheWeeklyChronicle/WC_1824/`. |
| `<TITLE_ID>` | The NBNP Digital Title ID | On <https://newspapers.lib.unb.ca>, search for the title, open its **Digital Issues at UNB Libraries** link, and read the ID from the URL: in `https://newspapers.lib.unb.ca/serials/browse/48`, the ID is `48`. |

## Import issues

1. SSH to HAMMER as the `imaging` user.
2. Attach to the shared `screen` session, so the import keeps running if you disconnect:

   ```sh
   screen -x NBNP
   ```

   If you see `There is no screen to be attached matching NBNP.`, no session is active.
   Create one:

   ```sh
   screen -S NBNP
   ```

3. Change to the NBNP scripts directory:

   ```sh
   cd /home/imaging/NBHP
   ```

4. Check the issues' metadata and page images:

   ```sh
   ./auditIssueMetadata.sh <PATH>
   ```

   When every issue is ready, the last line reads:

   ```text
   Metadata files within the path [<PATH>] do not contain any errors.
   ```

   If it reads `ERROR! Some issues within the path [<PATH>] have errors; they are listed
   above.`, the issues listed above that line will not be imported until their files are
   fixed. The other issues can still be imported.

5. Start the import:

   ```sh
   ./importNbnpIssue.sh <PATH> <TITLE_ID>
   ```

   It lists what it will do for each issue, ending with a summary such as
   `Create 8 issues, add to 0, 1 no-op, 0 blocked.`, then asks:

   ```text
   Import these issues into title <TITLE_ID>? [y/N]
   ```

6. Type `y` to import. Any other answer prints `Nothing was changed.` and stops.

The import runs at about 1,400 pages an hour; its summary gives the rate for each run. To
leave it running and disconnect, press Ctrl-A, then D. When it finishes it prints a summary,
then audits the issues it imported. A complete import ends like this, with your own counts:

```text
Import finished: The News, 1909
...
Created 9 issues, added to 0, 0 no-op, 0 blocked, 0 failed.
...
Audited 9 issues under <PATH>: 9 match the site, 0 differ, 0 could not be audited.
```

Its log and ledger are in `/home/imaging/NBHP/logs/nbnp-ingest/`, and the import summary is
sent to the NBHP notification topics.

## Check import progress

1. SSH to HAMMER as the `imaging` user.
2. Reattach to the session:

   ```sh
   screen -x NBNP
   ```

   You see the running import, or its summary if it has finished. If you see
   `There is no screen to be attached matching NBNP.`, the import was not started, or
   HAMMER was rebooted since it started; screen sessions do not survive a reboot. Start the
   import again (see [If an import fails or stops](#if-an-import-fails-or-stops)).

## Audit issues again later

An import audits its issues when it finishes. To audit a directory again later:

1. SSH to HAMMER as the `imaging` user.
2. Change to the NBNP scripts directory:

   ```sh
   cd /home/imaging/NBHP
   ```

3. Run the audit:

   ```sh
   ./auditNbnpIssue.sh <PATH> <TITLE_ID>
   ```

   The audit changes nothing, and saves its output in `/home/imaging/NBHP/logs/audits/`.
   Its last line begins with one of these:

   | Last line | Meaning |
   |---|---|
   | `AUDIT PASSED.` | Every issue matches the site. |
   | `AUDIT FOUND DISCREPANCIES.` | Some issues differ; the output above lists each difference. |
   | `AUDIT FAILED TO RUN` | The audit could not check at least one issue, or could not run at all, for example because the site could not be reached. The output above lists each issue it could not audit. |

An audit of The News for 1909, 636 pages, took about two minutes.

## If an import fails or stops

From `/home/imaging/NBHP`, inside the `NBNP` screen session (steps 1 to 3 of [Import
issues](#import-issues)), run the same command again:

```sh
./importNbnpIssue.sh <PATH> <TITLE_ID>
```

An import is safe to repeat. It finds the issues it already imported, finishes the ones it
left incomplete, and never imports an issue a second time. This applies whatever stopped
it: a failed issue, a lost connection, a reboot, or Ctrl-C.

| What you see | What to do |
|---|---|
| An issue marked `BLOCKED` in the plan, or `FAILED` in the results | Read the reason on that line, fix it (often a problem in the issue's `metadata.php` or page file names), then run the import again. |
| One issue needs finishing, such as missing tiles or PDFs | Edit `TITLE_ID` and `TITLE_PATH` at the top of `reingestIssue.sh`, then run `./reingestIssue.sh`. |
| An issue was deleted on the site on purpose and must be imported again | Ask the maintainer: it needs the tool's `--force` option. |

## Verify

Every issue matches the site: the import's audit line reports `0 differ, 0 could not be
audited.`, and a later `./auditNbnpIssue.sh` ends with `AUDIT PASSED.`. Open the title on
<https://newspapers.lib.unb.ca> and confirm the new issues are listed and their pages open
in the viewer.

## Related

- [nbnp-ingest README](../../tools/nbnp-ingest/README.md): what an import does, and the
  tool's commands and files.
