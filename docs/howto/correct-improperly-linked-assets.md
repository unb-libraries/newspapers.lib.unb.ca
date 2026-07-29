---
title: Correct improperly linked assets
status: active
owner: jsanford@unb.ca
last_reviewed: 2026-06-18
last_reviewed_by: jsanford@unb.ca
description: Move NBNP digital issues and titles between parents and re-index after correcting links.
---

# Correct improperly linked assets

## Goal

Correct NBNP digital issues and titles that are linked to the wrong parent entity, and
keep holding coverage and the Solr index consistent afterward.

## Prerequisites

- Drush access to the NBNP application (production or the relevant environment).
- An understanding of the NBNP content model — Publication, Digital Title, Digital
  Issue, Digital Page, and Holding. See [Architecture](../architecture.md#content-model).
- The entity IDs involved, gathered from the site or the database before you start.

!!! warning
    These operations move records and re-index search. Confirm the IDs before running
    each command, and run the holding-update and re-index steps so coverage and search
    stay correct.

## Move all issues within a Digital Title to a different Digital Title

1. Move the issues to the new Digital Title:

   ```sh
   drush eval '_newspapers_core_move_all_title_issues(48, 105)'
   ```

2. Update the holding coverage ranges for the affected titles:

   ```sh
   drush eval '_newspapers_core_update_holding_records([48, 108])'
   ```

3. Re-index both titles in Solr, since both changed:

   ```sh
   drush eval '_newspapers_core_reindex_title_issues([48, 105])'
   ```

## Move one Digital Issue to a different Digital Title

1. Find the Digital Title ID the issue is currently in (for example, `48`).
2. Move the issue to the new Digital Title:

   ```sh
   drush eval '_newspapers_core_move_issue_to_title(44036, 165)'
   ```

3. Update the holding coverage ranges for the affected titles:

   ```sh
   drush eval '_newspapers_core_update_holding_records([161, 165])'
   ```

4. Re-index the moved issue:

   ```sh
   drush eval "_newspapers_core_reindex_issues(['44036'])"
   ```

## Move multiple Digital Issues by date range

1. Move the issues that match a date range to the new title:

   ```sh
   drush eval "_newspapers_core_query_issues_to_title(\"SELECT id FROM digital_serial_issue WHERE parent_title=62 AND (STR_TO_DATE(issue_date, '%Y-%m-%d') BETWEEN '1904-09-30 23:59:59' AND '1905-02-06 23:59:59')\", 108)"
   ```

2. Update the holding coverage ranges for the affected titles:

   ```sh
   drush eval '_newspapers_core_update_holding_records([62, 108])'
   ```

3. Re-index both titles, since both changed:

   ```sh
   drush eval '_newspapers_core_reindex_title_issues([62, 108])'
   ```

## Link a Digital Title to a different Publication

1. Find the Digital Title ID you want to move (for example, `48`).
2. Find the Publication node ID to unlink the title from (for example, `272`) and the
   Publication node ID to link it to (for example, `1468`).
3. Point the Digital Title's parent at the new Publication:

   ```sql
   UPDATE digital_serial_title SET parent_title=1468 WHERE parent_title=272;
   ```

4. Point the existing holding records at the new Publication:

   ```sql
   UPDATE serial_holding SET parent_title=1468 WHERE parent_title=272 AND holding_coverage='Digital Issues at UNB Libraries';
   ```

5. Update the holding coverage ranges for the title:

   ```sh
   drush eval '_newspapers_core_update_holding_records([48])'
   ```

6. Re-index all the issues for the title in Solr:

   ```sh
   drush eval '_newspapers_core_reindex_title_issues([48])'
   ```

## Worked example: batch a correction with a script

For a real correction it is often easiest to collect the calls in a `move.php` script
and run it with `drush scr`, rather than running each `drush eval` by hand.

### 19872: move issues from Morning Star to The Star (Fredericton)

The Morning Star digital issues for August–December 1879 belong under The Star
(Fredericton). `move.php`:

```php
<?php

_newspapers_core_query_issues_to_title("SELECT id FROM digital_serial_issue WHERE parent_title=126 AND (STR_TO_DATE(issue_date, '%Y-%m-%d') BETWEEN '1879-08-01 00:00:01' AND '1879-12-31 23:59:59')", 91);
_newspapers_core_update_holding_records([126, 91]);
_newspapers_core_reindex_title_issues([126, 91]);
```

```sh
drush scr move.php
```

### Move issues from Glassville News and Aberdeen and Kent Pioneer to The Glassville News

The September 1893 – May 1896 digital issues belong under The Glassville News.
`move.php`:

```php
<?php

_newspapers_core_query_issues_to_title("SELECT id FROM digital_serial_issue WHERE parent_title=121 AND (STR_TO_DATE(issue_date, '%Y-%m-%d') BETWEEN '1893-09-01 00:00:01' AND '1896-05-31 23:59:59')", 77);
_newspapers_core_update_holding_records([121, 77]);
_newspapers_core_reindex_title_issues([121, 77]);
```

```sh
drush scr move.php
```

## Verify the result

Confirm the moved issues and titles appear under the correct parent at
<https://newspapers.lib.unb.ca>, and that the holding statement and date coverage are
correct. Search the affected titles to confirm the Solr index reflects the change.
