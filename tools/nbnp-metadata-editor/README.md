# NBNP metadata editor

A form for creating and editing the `title.toml` and `issue.toml` files that
[nbnp-ingest](../nbnp-ingest/README.md) imports, started by right-clicking a folder in Windows
Explorer. It writes the files nbnp-ingest already reads; it changes nothing about the files'
format, the import or the site.

The form checks each value against the cataloguing guidance (values as printed, no zero
padding, missing pages as numbers and ranges) and against what nbnp-ingest accepts, so a saved
file imports without metadata errors.

## Requirements

- Windows 10 or 11, with Windows PowerShell 5.1, which both include.
- Write access to the newspaper folders.
- No administrator rights: the menu is installed for the signed-in user only.

## Install

1. Download the ZIP file of the newest **nbnp-metadata-editor** release from
   <https://github.com/unb-libraries/newspapers.lib.unb.ca/releases?q=nbnp-metadata-editor>.
2. Extract it somewhere it will stay, such as your Documents folder. The menu runs the editor
   from there.
3. In the extracted `nbnp-metadata-editor` folder, double-click **Install.cmd**. If Windows
   asks whether to run it, choose **Run** (or **More info**, then **Run anyway**). A window
   says `Installed for <you>.`; press a key to close it.

To update, do the same with the new release: extract it and double-click its Install.cmd.
The menu then runs the new copy, and the window names the folder of the old one, which you can
delete. Extracting over the old folder works too, but still double-click Install.cmd: Windows
puts each ZIP file in a folder named after it by default, so the menu may still point at the
old copy, and the installer unblocks the new files.
After moving the folder, double-click Install.cmd again in its new place.

## Use

Right-click any folder of a title, or the empty space inside one, and choose **Edit newspaper
metadata…** (on Windows 11 under **Show more options**, or Shift+right-click). One window
opens, with two views.

### The title view

It shows the title's `title.toml`, the first page of its first issue, and its issues:

- **Title metadata**: Title ID, Title, Language and Media. Issues cannot be entered until the
  title has a `title.toml`. If it has none yet, the window says so and proposes the folder for
  it: the first folder up from the issues whose name does not share their short name (for
  `KCR_1902_17_26`, the first folder not starting `KCR_`). **Use another folder for the
  title...** picks a different one.
- **Issues**: how many issue folders the title has (folders with page images), how many are
  done, need metadata, or are already imported, and the list of them.
  Double-click one to open it.
- **Save and enter issues** (Enter) saves the title and opens the first issue that needs
  metadata, or the issue the menu was opened on.

Opening the menu on an issue folder of a title that has its `title.toml` goes straight to that
issue.

Open the menu on a title's folder or on any folder inside it. When there is no `title.toml` at
or above the folder, the editor refuses, with a message and no window, a folder that is above a
title: one with a `title.toml` below it, one whose first issue belongs to a title folder below
it (the folder of all titles, a desktop), and one with no issue folder within 6 levels or the
first 2,000 folders below it.

### The issue view

The header shows the title, the issue, and where it is in the title (issue 5 of 52; 47 need
metadata). On the right is the issue's page viewer: the first page, fitted to the width at the
top so the masthead shows first.

| In the viewer | Does |
|---|---|
| Mouse wheel | Zooms in or out at the pointer. |
| Drag | Moves the page. |
| Double-click, or **Fit** | Fits the page to the width again. |
| **+**, **-** | Zoom in or out. |
| The page list | Shows another page of the issue. |
| **Open** | Opens the page in the Windows viewer. |

On the left, the fields follow the masthead: volume, issue number, date.

1. Type the volume, issue and date as printed, and anything else the issue needs.
2. Press **Enter** (or Ctrl+S) for **Save and next**: the issue is saved and the next issue of
   the title opens, in name order across year and volume folders, skipping issues already
   imported.
3. A new issue arrives filled in from the one just saved, except missing pages and errata,
   with the issue number selected: type the new number, check the date, press Enter.

After the last issue the button reads **Save and finish** and returns to the title view, with
the issues still needing metadata counted. **Back to title** (Alt+B) returns at any time.
**Save** saves and stays; **Close** (Esc) closes the window. Leaving a view or closing with
unsaved changes asks first.

A folder named like its issue plus `_Sup`, `_Sup2` or `_S1` is taken as that issue's
supplement: it opens with **This is a supplement** ticked and the issue's date, volume and
number filled in, ready for the supplement's title.

**Fill from last saved issue** (Alt+F) does the same filling on demand, for example when
starting again the next day. It is offered only when the last issue you saved belongs to the
same title. The last issue is remembered for you alone, in
`%LOCALAPPDATA%\nbnp-metadata-editor\last-issue.toml`.

The buttons and messages stay at the bottom of the window whatever its size; on a small screen
the fields scroll.

### Checks

A value that needs correcting is marked in red, with the reason at the bottom, as soon as you
leave the field; nothing is saved until it is fixed. A warning asks whether to save anyway:

- the volume, issue and date are the same as the last issue you saved, usually a filled-in
  form saved without changing the number;
- the date's year differs from a year in the folder name, or is before 1600;
- a value contains invisible characters, often pasted with text.

### The fields

| Field | What to enter |
|---|---|
| Title ID | The Digital Title ID: in `https://newspapers.lib.unb.ca/serials/browse/48` it is `48`. Leave it empty if it is not known yet: the file saves with a warning, but nbnp-ingest refuses to import the title's issues (`missing field title_id`) until it is filled in. |
| Title | The exact, complete title from the masthead. In an issue, only when it differs from the title's. |
| Language | `eng` or `fre`. |
| Date | As printed. Written `YYYY-MM-DD`, such as `1909-01-29`; `1909-1-29`, `January 29, 1909` and `29 January 1909` are also accepted and rewritten. Forms like `03/04/1909`, where day and month could be either way round, are refused. If the day is not printed (a monthly) or you are unsure of it, use the 1st. The weekday is shown under it as a check. |
| Volume, Issue | Exactly as printed, even if wrong; note a wrong number in Errata. No zero padding: `5`, not `05`. |
| Edition | Optional: the edition statement as printed. |
| This is a supplement | Tick for a supplement. Its date, volume and issue are those of the issue it came with; for a folder named like `TN_1909_33_05_Sup` they are copied from `TN_1909_33_05` when that folder has an `issue.toml`. |
| Supplement title | The supplement's title as printed, if it has one. |
| Missing pages | Page numbers separated by commas, or ranges: `3, 6, 7` or `4-8`. Numbers, dashes and commas only. |
| Errata | Short and clear to everyone. For any problem with printed issue numbering, use "Incorrect issue numbering" (the button inserts it). |

Any script can be entered: accented and Indigenous-language letters, right-to-left and CJK
text, and characters outside the Basic Multilingual Plane. Accents are stored joined to their
letter (`é` as one character), as Windows types them, so pasted text compares and searches
alike. Invisible characters that often arrive with pasted text, such as zero-width spaces,
draw a warning naming them.

The **Different from the title...** fields are hidden until ticked, and usually left empty;
fill them only when an issue differs from its title, for example a masthead printing a
different title. They open by themselves when an issue already uses them, or when no
`title.toml` is found.

Each field's hint is short; hover over a field for the full guidance.

### Warnings on save

Save compares the issue with the title's other issues that have an `issue.toml`, and asks
before saving if:

- the date is earlier than the previous issue's or later than the next one's (folder order);
- another issue has the same date (and edition), or the same volume and issue;
- the volume or issue differs from the folder name's (`KCR_1902_17_26`: volume 17, issue 26);
- the volume or issue is lower than the previous issue's;
- the edition is only a number.

Supplements are not compared with other issues. When Errata is filled in, the number checks
are skipped, since the problem is already noted there. **Save anyway** keeps the values as
typed: the metadata records what was printed.

**Warnings...** (Alt+W, in the issue view) turns any of these warnings off, or back on, for
good: untick it and press OK. The same list covers the "same as the last issue you saved"
warning, a year that differs from the folder path's, and a very early date. The choice is
kept for you alone, in `%LOCALAPPDATA%\nbnp-metadata-editor\settings.json`. Errors, which
stop a save, cannot be turned off.

### When the form will not edit a file

- **An issue already imported**: the form says so and does not open it.
- **A file the form cannot read completely**: for example one with keys or TOML features these
  files do not use. The form shows it read-only with the reason, rather than save it and lose
  what it did not understand.

### Saving and backups

The file is written in full to a temporary file in the same folder, then put in place, so an
interrupted save leaves the old file as it was. The version it replaces is kept as
`issue.toml.bak` (or `title.toml.bak`); each save replaces the previous `.bak`.

If someone else changed or created the file while the form was open, Save refuses and nothing
is written. Close the form and open it again to see their version.

## Uninstall

Double-click **Uninstall.cmd** in the folder. This removes the menu item (and those of earlier versions) and nothing else; metadata files are not touched. Then delete
the folder if you no longer need it.

## Troubleshooting

| Problem | What to do |
|---|---|
| The menu item is missing | On Windows 11, look under **Show more options**. Otherwise double-click Install.cmd again. |
| Choosing it does nothing | The folder was probably moved; double-click Install.cmd again from where it is now. To see an error, run `powershell.exe -NoProfile -ExecutionPolicy Bypass -STA -File .\Edit-NbnpMetadata.ps1 -Path "<folder>"` in a PowerShell window. |
| "This folder could not be found" | The folder was renamed or the share disconnected after right-clicking; try again. |
| "Nothing was saved" with an access error | You cannot write to that folder, or the file is open in another program. |

## For maintainers

| File | Does |
|---|---|
| `NbnpMetadata.psm1` | Reads, checks and writes the files; no user interface. |
| `Edit-NbnpMetadata.ps1` | The form (Windows Forms). |
| `Install-ContextMenu.ps1`, `Uninstall-ContextMenu.ps1` | The menu item, under `HKCU\Software\Classes\Directory\shell` and `...\Directory\Background\shell`. |
| `Install.cmd`, `Uninstall.cmd` | Run those two scripts on a double-click, with `-ExecutionPolicy Bypass` for that one process; the computer's policy is not changed. |
| `package.sh` | Builds the release ZIP file. |
| `tests/` | Pester tests, their fixtures, and `run-tests.sh`. |

An issue folder with a `metadata.php` and no `issue.toml` is shown to users as "already
imported" and cannot be opened: the editor only creates new metadata, and users do not need to
know about the older format.

The file format is defined by nbnp-ingest (`tools/nbnp-ingest/src/domain/metadata.rs`): when it
changes, change `NbnpMetadata.psm1` and the fixtures with it. The scripts must stay ASCII,
because Windows PowerShell 5.1 reads a script without a byte order mark as ANSI; a test checks
this.

### Releasing

Set `$script:Version` in `Edit-NbnpMetadata.ps1`, commit, then tag and push:

```sh
git tag -a nbnp-metadata-editor-v0.1.0 -m "nbnp-metadata-editor 0.1.0"
git push origin nbnp-metadata-editor-v0.1.0
```

The workflow runs the tests, then `package.sh` (which refuses a tag that does not match the
version) and publishes `nbnp-metadata-editor-<version>.zip` and its `.sha256` as a release,
not marked latest, so the repository's latest release stays nbnp-ingest. The version shows in
the window's title bar.

### Tests

```sh
tools/nbnp-metadata-editor/tests/run-tests.sh
```

Needs Docker and cargo. It checks the scripts with PSScriptAnalyzer for Windows PowerShell 5.1
compatibility, runs the Pester tests in a PowerShell 7 container, and runs `nbnp-ingest check`
over every valid issue fixture as the editor saves it. CI runs the same script
(`.github/workflows/nbnp-metadata-editor.yaml`).

The form, the menu and Windows PowerShell 5.1 itself are not tested automatically. Check them
on a Windows PC with the list below after changing the form or the installer.

### Windows smoke checklist

Run it on the sample data set: a folder `NBNP` holding three page images from each of a few
real issues, with each issue's old `metadata.php` kept beside it as `metadata.php.reference`
to check the values against. It is not in this repository; ask for it.

1. Install from the release ZIP file with Install.cmd: one **Edit newspaper metadata…** item appears on a folder and inside an open
   folder (Windows 11: under Show more options). If an earlier version was installed, its two
   items are gone.
2. **No title yet.** Open the menu on `KingsCountyRecord\KCR_1902\KCR_1902_17\KCR_1902_17_26`:
   the title view opens, says there is no `title.toml`, and proposes `KingsCountyRecord`. It
   lists 3 issue folders, 3 needing metadata, and shows the first page of `KCR_1902_16_25`.
   Enter ID `332`, title `The Kings County Record`, and press Enter: `KCR_1902_17_26` opens
   (the folder the menu was opened on).
3. **Viewer.** The page shows at the window's width from the top. The wheel zooms at the
   pointer, dragging moves it, double-click fits it, the page list shows pages 2 and 3, Open
   opens the page in Windows. Resize the window: the page refits until zoomed or moved.
4. **A run.** Open the menu on `The_News`: the title view lists 3 issues to do. Press Enter:
   `TN_1909_33_05` opens with the cursor in Volume; Tab goes Volume, Issue, Date, Edition,
   Missing pages, Errata. Enter the values and press Enter: `TN_1909_33_06` opens, says so in
   green, filled in, with the issue number selected.
5. **Warnings.** On `TN_1909_33_06`, press Enter without changing anything: warnings that the
   issue differs from the folder name and that `TN_1909_33_05` has the same date and number.
   Answer No. Type an errata note: only the same-date warning remains. Clear it, correct the
   number and date, press Enter. Edition `6` warns that it is only a number. On
   `TN_1909_33_07` the button reads Save and finish; pressing it returns to the title view:
   0 issues need metadata.
6. **Across volumes.** In Kings County Record, Save and next goes `KCR_1902_16_25`,
   `KCR_1902_17_26`, `KCR_1902_18_27`.
7. **Supplement.** In The Quoddy Tides (a `title.toml` without a Title ID: the title view
   warns on save), save `QT_1999_31_15`: `QT_1999_31_15_Sup` opens ticked with `_15`'s date,
   volume and number; type a supplement title, press Enter: `QT_1999_31_16` opens unticked.
8. **Dates.** Type `1987-10-1`, `October 1, 1987` and `1 Oct 1987` in turn and leave the field:
   each becomes `1987-10-01` with its weekday. `01/10/1987` is marked red.
9. **Checks as you go.** Volume `05`, missing pages `1 & 2` and date `1909-02-29` turn red on
   leaving the field; nothing saves until they are fixed. The buttons stay visible however
   small the window.
10. **Text.** Enter an edition with `"quotes"`, a backslash, `é`, `Miʼkmaq`, `جريدة` and `𝔄`,
    and two lines of errata; save and reopen: unchanged and displayed correctly. Saving an
    existing issue again leaves `issue.toml.bak`.
11. **Leaving and conflicts.** Back to title or Close after a change asks first. Change an
    open issue's `issue.toml` in Notepad, then save in the window: refused, Notepad's version
    kept.
12. **Awkward names.** Open the menu on `Tèst Title (O'Brien) – 新聞`: the title view opens on
    that folder.
13. **Already imported.** In Agenutemagen, the issue list shows `AG_1974_03_09` as imported;
    double-clicking it says it has already been imported and can't be changed here.
14. **Update.** Extract the ZIP file again to another folder and double-click its
    Install.cmd: it says the menu ran the editor from the old folder, and the menu opens the
    new copy (its version is in the title bar). Run Install.cmd again: "Updated in place."
15. **Above a title.** Open the menu on the kit's `NBNP` folder, on the desktop, and on a
    drive: each shows a message saying to open it from a title's folder, and no window.
16. **Warnings settings.** Press Warnings..., untick "Same date as another issue of the
    title", OK. Give an issue the date of the issue before it: no same-date warning. Close the
    editor, open it again: still off. Tick it again.
17. **Fill from another title.** Save an issue of The News, then open an issue of Kings County
    Record: Fill from last saved issue is greyed out.
18. Uninstall: the menu item disappears; the metadata files remain.
