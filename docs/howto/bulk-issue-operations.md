---
title: Bulk issue operations
status: active
owner: jsanford@unb.ca
last_reviewed: 2026-06-18
last_reviewed_by: jsanford@unb.ca
description: Import and validate NBNP newspaper issues in bulk from the HAMMER file server.
---

# Bulk issue operations

## Goal

Import digitized newspaper issues into NBNP in bulk, and validate issues after import.

## Prerequisites

- SSH access to the HAMMER file server as the `imaging` user.
- The full path on HAMMER to the issues you want to import. Although the tool can
  import thousands of issues at once, import in smaller groups (for example, one
  year of issues at a time).
- The NBNP Digital Title ID the issues attach to. Find it by visiting
  <https://newspapers.lib.unb.ca>, running a title search, opening the **Digital
  Issues at UNB Libraries** link, and reading the ID from the URL — for example, from
  `https://newspapers.lib.unb.ca/serials/browse/48`, the ID is `48`.

In the examples below the import path is `/mnt/nbnp/TheWeeklyChronicle/WC_1824/`
(include the trailing slash) and the title ID is `48`.

## Import issues

1. SSH to HAMMER as the `imaging` user.
2. Attach to the shared `screen` session so the import survives a disconnect:

   ```sh
   screen -x NBNP
   ```

   If you see `There is no screen to be attached matching NBNP.`, no session is
   active — create one:

   ```sh
   screen -S NBNP
   ```

3. Change to the NBNP tools directory:

   ```sh
   cd /home/imaging/NBHP
   ```

4. Pre-audit the issue metadata before importing — many metadata problems can be
   caught up front:

   ```sh
   ./auditIssueMetadata.sh /mnt/nbnp/TheWeeklyChronicle/WC_1824/
   ```

   On success a confirmation message appears. On failure the output names the
   relevant `metadata.php` file and the problem.

5. Run the import, passing the path and the title ID:

   ```sh
   ./importNbnpIssue.sh /mnt/nbnp/TheWeeklyChronicle/WC_1824/ 48
   ```

6. Review the confirmation screen listing the directories to be processed, then type
   `y` to continue:

   ```text
   ?  The Create Issues will be applied to ALL of the above directories. Are you sure you want to continue? (y/n)
   ```

The import can take hours depending on the number of issues. Because it runs inside
`screen`, it is safe to disconnect from SSH while it runs.

## Check import progress

1. SSH to HAMMER as the `imaging` user.
2. Reattach to the session:

   ```sh
   screen -x NBNP
   ```

   You will see the running import or a completion message. If you instead see
   `There is no screen to be attached matching NBNP.`, the import was not started or
   the server was rebooted since it started — screen sessions do not survive reboots.
   Contact the maintainer.

## Validate imported issues

Determine the path and title ID as above, then:

1. SSH to HAMMER as the `imaging` user.
2. Change to the home directory:

   ```sh
   cd /home/imaging
   ```

3. Run the audit, passing the path and the title ID:

   ```sh
   ./auditNbnpIssue.sh /mnt/nbnp/TheWeeklyChronicle/WC_1824/ 48
   ```

## Verify the result

The audit reports success when imported issues are valid. On HAMMER it takes about
10 minutes per 1,000 pages. Confirm the issues appear under the expected title at
<https://newspapers.lib.unb.ca>.
