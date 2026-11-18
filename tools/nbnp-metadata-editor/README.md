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
   `%LOCALAPPDATA%\Programs\nbnp-metadata-editor`. The menu runs the editor from there.
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

### Entering a run of issues

Right-click the first issue's folder and open the issue form. On the right it shows the top of
the folder's first page, so the masthead can be read while typing; click it to open the whole
page. On the left, the fields follow the masthead: volume, issue number, date.

1. Type the volume, issue and date as printed, and anything else the issue needs.
2. Press **Enter** (or Ctrl+S) for **Save and next**. The issue is saved and the form moves to
   the next issue folder, in name order and on into the next volume's folder, skipping folders
   that still have a `metadata.php`.
3. A new issue arrives filled in from the one just saved, except missing pages and errata,
   with the issue number selected: type the new number, check the date, and press Enter.

The green line at the top says what was saved and where the form is now. **Save and close**
saves and stops; **Cancel** (Esc) closes without saving, asking first if anything was typed.

A folder named like its issue plus `_Sup`, `_Sup2` or `_S1` is taken as that issue's supplement:
it opens with **This is a supplement** ticked and the issue's date, volume and number filled
in, ready for the supplement's title.

**Fill from last saved issue** (Alt+F) does the same filling on demand, for example when
starting again the next day. The last issue is remembered for you alone, in
`%LOCALAPPDATA%\nbnp-metadata-editor\last-issue.toml`.

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

Run it on the sample data set: a folder `NBNP` holding three page images from each of a few
real issues, with each issue's old `metadata.php` kept beside it as `metadata.php.reference`
to check the values against. It is not in this repository; ask for it.

1. Install; both menu items appear on a folder and inside an open folder (Windows 11: under
   Show more options).
2. **Title form.** `The_News` opens with its four values. On `KingsCountyRecord`, create a
   `title.toml` with ID `332`, title `The Kings County Record`, `eng`, `print`: four lines,
   `title_id = 332` without quotes. On `Gigmanag`, save with the ID empty: a warning, then no
   `title_id` line.
3. **A run.** Open the issue form on `The_News\TN_1909\TN_1909_33\TN_1909_33_05`. The masthead
   shows on the right within a second or two; clicking it opens the page. The cursor is in
   Volume, and Tab goes Volume, Issue, Date, Edition, Missing pages, Errata. Enter the values
   and press Enter: the form moves to `TN_1909_33_06`, says so in green, and arrives filled in
   with the issue number selected.
4. **Repeat guard.** On `TN_1909_33_06`, press Enter without changing anything: a warning that
   it repeats `TN_1909_33_05`. Answer No, correct the number and date, press Enter. On
   `TN_1909_33_07`, Save and next is greyed out (no issue after it); Save and close ends.
5. **Across volumes.** From `KCR_1902_16_25`, Save and next goes to `KCR_1902_17_26`, then
   `KCR_1902_18_27`.
6. **Supplement.** Save `QT_1999_31_15`, press Enter: `QT_1999_31_15_Sup` opens with the
   supplement box ticked and `_15`'s date, volume and number; type a supplement title and press
   Enter: `QT_1999_31_16` opens with the box clear.
7. **Dates.** Type `1987-10-1`, `October 1, 1987` and `1 Oct 1987` in turn and leave the field:
   each becomes `1987-10-01` with its weekday. `01/10/1987` is marked red.
8. **Checks as you go.** Volume `05`, missing pages `1 & 2` and date `1909-02-29` turn red on
   leaving the field, with the reason at the bottom; nothing saves until they are fixed.
9. **Text.** Enter an edition with `"quotes"`, a backslash, `é`, `Miʼkmaq`, `جريدة` and `𝔄`,
   and two lines of errata; save and reopen: unchanged and displayed correctly. Reopening an
   issue and saving it again leaves `issue.toml.bak`.
10. **Cancel and conflicts.** Cancel after a change asks first and writes nothing. Open a form,
    change its `issue.toml` in Notepad, then save in the form: refused, Notepad's version kept.
11. **Awkward names, no title file.** Open `Tèst Title (O'Brien) – 新聞\TT_1900\TT_1900_01\TT_1900_01_01`:
    the form opens, says no `title.toml` was found, and shows the Different from the title
    fields with `eng` and `print` filled in.
12. **Legacy.** `Agenutemagen\AG_1974\AG_1974_03\AG_1974_03_09` opens its `metadata.php` read-only.
13. Uninstall: both menu items disappear; the metadata files remain.
