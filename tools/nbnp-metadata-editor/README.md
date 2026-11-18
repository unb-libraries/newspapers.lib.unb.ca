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

1. Copy the `nbnp-metadata-editor` folder somewhere it will stay, for example
   `%LOCALAPPDATA%\nbnp-metadata-editor`. The menu runs the editor from there.
2. In that folder, open a PowerShell window and run:

   ```powershell
   powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\Install-ContextMenu.ps1
   ```

   It prints `Installed for <user>.` The `-ExecutionPolicy Bypass` applies to that one command;
   the computer's policy is not changed. The installer also unblocks the scripts, in case they
   came from a downloaded ZIP file.

After moving or deleting the folder, run the installer again from the new place, or uninstall.

## Use

Right-click a folder, or the empty space inside an open folder, and choose:

| Menu item | Use it on | Creates or edits |
|---|---|---|
| Edit newspaper title metadata… | A title's folder, such as `The_News` | `title.toml` |
| Edit newspaper issue metadata… | An issue's folder, such as `TN_1909_33_05` | `issue.toml` |

On Windows 11 both items are under **Show more options** (or hold Shift while right-clicking).

Create the title's `title.toml` first: each issue inherits the title, language and media from
the nearest `title.toml` in the folders above it, and the issue form shows those values.

Fill in the form and choose **Save**. Values that need correcting are marked in red with the
reason; nothing is written until they are fixed. A warning, such as a date whose year differs
from the year in the folder name, asks whether to save anyway. **Cancel** closes without
writing anything.

### The fields

| Field | What to enter |
|---|---|
| Title ID | The Digital Title ID: in `https://newspapers.lib.unb.ca/serials/browse/48` it is `48`. |
| Title | The exact, complete title from the masthead. In an issue, only when it differs from the title's. |
| Language | `eng` or `fre`. |
| Date | `YYYY-MM-DD`, as printed, such as `1909-01-29`. If the day is not printed (a monthly) or you are unsure of it, use the 1st. The weekday is shown beside it as a check. |
| Volume, Issue | Exactly as printed, even if wrong; note a wrong number in Errata. No zero padding: `5`, not `05`. |
| Edition | Optional: the edition statement as printed. |
| This is a supplement | Tick for a supplement. Its date, volume and issue are those of the issue it came with; for a folder named like `TN_1909_33_05_Sup`, ticking the box copies them from `TN_1909_33_05` when that folder has an `issue.toml`. |
| Supplement title | The supplement's title as printed, if it has one. |
| Missing pages | Page numbers separated by commas, or ranges: `3, 6, 7` or `4-8`. Numbers, dashes and commas only. |
| Errata | Short and clear to everyone. For any problem with printed issue numbering, use "Incorrect issue numbering" (the button inserts it). |

The **Different from the title** fields are usually left empty; fill them only when an issue
differs from its title, for example a masthead printing a different title.

### When the form will not edit a file

- **A folder with `metadata.php`**: older issues keep their metadata in that file. The form
  shows it read-only; edit it as before.
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

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\Uninstall-ContextMenu.ps1
```

This removes the two menu items and nothing else; metadata files are not touched. Then delete
the folder if you no longer need it.

## Troubleshooting

| Problem | What to do |
|---|---|
| The menu items are missing | On Windows 11, look under **Show more options**. Otherwise run the installer again. |
| Choosing an item does nothing | The folder was probably moved; run the installer again from where it is now. To see an error, run `powershell.exe -NoProfile -ExecutionPolicy Bypass -STA -File .\Edit-NbnpMetadata.ps1 -Kind Issue -Path "<folder>"` in a PowerShell window. |
| "This folder could not be found" | The folder was renamed or the share disconnected after right-clicking; try again. |
| "Nothing was saved" with an access error | You cannot write to that folder, or the file is open in another program. |

## For maintainers

| File | Does |
|---|---|
| `NbnpMetadata.psm1` | Reads, checks and writes the files; no user interface. |
| `Edit-NbnpMetadata.ps1` | The form (Windows Forms). |
| `Install-ContextMenu.ps1`, `Uninstall-ContextMenu.ps1` | The menu items, under `HKCU\Software\Classes\Directory\shell` and `...\Directory\Background\shell`. |
| `tests/` | Pester tests, their fixtures, and `run-tests.sh`. |

The file format is defined by nbnp-ingest (`tools/nbnp-ingest/src/domain/metadata.rs`): when it
changes, change `NbnpMetadata.psm1` and the fixtures with it. The scripts must stay ASCII,
because Windows PowerShell 5.1 reads a script without a byte order mark as ANSI; a test checks
this.

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

1. Install; both items appear on a folder and inside an open folder (Windows 11: under Show
   more options).
2. On a new title folder, create a `title.toml` with ID `48`, title `The News`, language
   `eng`, media `print`. Open the file: four lines, `title_id = 48` without quotes.
3. On a new issue folder below it, create an `issue.toml` with date `1909-01-29`, volume `33`,
   issue `5`. The form shows the inherited title; the file has three lines and no empty fields.
4. Reopen it: the values are filled in. Change the issue to `6` and save: `issue.toml.bak`
   holds the previous version.
5. Try title ID `-1`, `4.5` and `abc`, date `1909-02-29`, volume `05`, missing pages `1 & 2`:
   each is marked in red and nothing is saved.
6. Enter an edition with `"quotes"`, a backslash and `é`, and two lines of errata; save and
   reopen: the values are unchanged.
7. Cancel after a change: asked to confirm; the file is unchanged.
8. Open the form, change the file in Notepad, then Save in the form: refused, Notepad's version
   kept.
9. Right-click a folder whose name has spaces, an apostrophe, parentheses and `é`: the form
   opens on that folder.
10. A folder with `metadata.php` opens read-only.
11. Uninstall: both items disappear; the metadata files remain.
