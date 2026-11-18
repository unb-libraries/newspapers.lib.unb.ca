#Requires -Version 5.1
<#
.SYNOPSIS
    Opens a form to create or edit the title.toml or issue.toml in a folder.

.DESCRIPTION
    Started from the Explorer right-click menu (Install-ContextMenu.ps1). The folder is only
    ever used as a path, never run as code. Nothing is written until Save, and Save refuses
    values nbnp-ingest would not accept.

.PARAMETER Kind
    Title for title.toml, Issue for issue.toml.

.PARAMETER Path
    The folder the file belongs in.
#>
[CmdletBinding()]
param(
    [Parameter(Mandatory)][ValidateSet('Title', 'Issue')][string] $Kind,
    [Parameter(Mandatory)][string] $Path
)

Set-StrictMode -Version 2.0
$ErrorActionPreference = 'Stop'

Add-Type -AssemblyName System.Windows.Forms, System.Drawing
[Windows.Forms.Application]::EnableVisualStyles()
Import-Module (Join-Path $PSScriptRoot 'NbnpMetadata.psm1') -Force

$script:FileKind = $Kind
$script:AppTitle = if ($Kind -eq 'Title') { 'Title Metadata' } else { 'Issue Metadata' }

function Show-Message([string] $Text, [string] $Icon = 'Information', [string] $Buttons = 'OK') {
    [Windows.Forms.MessageBox]::Show($Text, $script:AppTitle, $Buttons, $Icon)
}

# Explorer passes "%V"; a folder ending in \ (a drive root) arrives with a stray quote.
$Path = $Path.Trim().Trim('"').TrimEnd('\')
if ($Path -match '^[A-Za-z]:$') { $Path += '\' }
if (-not (Test-Path -LiteralPath $Path -PathType Container)) {
    [void](Show-Message "This folder could not be found:`n$Path" 'Error')
    exit 1
}
$Path = (Get-Item -LiteralPath $Path).FullName

#region Read-only views

function Show-ReadOnly([string] $Heading, [string] $FilePath, [string] $Text) {
    $form = New-Object Windows.Forms.Form
    $form.Text = "$($script:AppTitle) (read only)"
    $form.StartPosition = 'CenterScreen'
    $form.Size = New-Object Drawing.Size(720, 520)
    $form.Font = New-Object Drawing.Font('Segoe UI', 9)
    $form.AutoScaleMode = 'Dpi'

    $layout = New-Object Windows.Forms.TableLayoutPanel
    $layout.Dock = 'Fill'
    $layout.Padding = New-Object Windows.Forms.Padding(12)
    $layout.RowCount = 4
    [void]$layout.RowStyles.Add((New-Object Windows.Forms.RowStyle('AutoSize')))
    [void]$layout.RowStyles.Add((New-Object Windows.Forms.RowStyle('AutoSize')))
    [void]$layout.RowStyles.Add((New-Object Windows.Forms.RowStyle('Percent', 100)))
    [void]$layout.RowStyles.Add((New-Object Windows.Forms.RowStyle('AutoSize')))

    $label = New-Object Windows.Forms.Label
    $label.Text = $Heading
    $label.AutoSize = $true
    $label.MaximumSize = New-Object Drawing.Size(680, 0)
    $layout.Controls.Add($label)

    $file = New-Object Windows.Forms.TextBox
    $file.Text = $FilePath
    $file.ReadOnly = $true
    $file.Dock = 'Fill'
    $layout.Controls.Add($file)

    $content = New-Object Windows.Forms.TextBox
    $content.Multiline = $true
    $content.ReadOnly = $true
    $content.ScrollBars = 'Both'
    $content.WordWrap = $false
    $content.Font = New-Object Drawing.Font('Consolas', 9)
    $content.Dock = 'Fill'
    $content.Text = $Text -replace "(?<!`r)`n", "`r`n"
    $layout.Controls.Add($content)

    $close = New-Object Windows.Forms.Button
    $close.Text = 'Close'
    $close.Anchor = 'Right'
    $close.DialogResult = 'Cancel'
    $layout.Controls.Add($close)
    $form.CancelButton = $close

    $form.Controls.Add($layout)
    [void]$form.ShowDialog()
}

$legacy = Join-Path $Path 'metadata.php'
if ($Kind -eq 'Issue' -and (Test-Path -LiteralPath $legacy -PathType Leaf)) {
    Show-ReadOnly ("This issue's metadata is in the older metadata.php file, so it cannot be edited here. " +
        'Edit metadata.php as before; it is shown below.') $legacy ([IO.File]::ReadAllText($legacy))
    exit 0
}

$file = Read-NbnpMetadataFile -Folder $Path -Kind $Kind
if (-not $file.Supported) {
    Show-ReadOnly ("This file cannot be edited here, so that nothing in it is lost: $($file.Reason). " +
        'Ask whoever maintains the newspaper metadata to look at it.') $file.Path $file.Text
    exit 0
}

#endregion

#region Form

$script:TitleFile = $null
$script:TitleFilePath = $null
if ($Kind -eq 'Issue') {
    $script:TitleFilePath = Find-NbnpTitleFile -IssueFolder $Path
    if ($script:TitleFilePath) {
        $script:TitleFile = Read-NbnpMetadataFile -Folder (Split-Path -Parent $script:TitleFilePath) -Kind Title
        if (-not $script:TitleFile.Supported) { $script:TitleFile = $null }
    }
}

$form = New-Object Windows.Forms.Form
$form.Text = "$($script:AppTitle): $(Split-Path -Leaf $Path)"
$form.StartPosition = 'CenterScreen'
$form.Font = New-Object Drawing.Font('Segoe UI', 9)
$form.AutoScaleMode = 'Dpi'
$form.AutoScroll = $true
$form.ClientSize = New-Object Drawing.Size(760, 700)
$form.MinimumSize = New-Object Drawing.Size(600, 400)

$errorProvider = New-Object Windows.Forms.ErrorProvider
$errorProvider.BlinkStyle = 'NeverBlink'

$grid = New-Object Windows.Forms.TableLayoutPanel
$grid.ColumnCount = 2
[void]$grid.ColumnStyles.Add((New-Object Windows.Forms.ColumnStyle('AutoSize')))
[void]$grid.ColumnStyles.Add((New-Object Windows.Forms.ColumnStyle('Percent', 100)))
$grid.Dock = 'Top'
$grid.AutoSize = $true
$grid.Padding = New-Object Windows.Forms.Padding(12, 12, 24, 12)

$script:Inputs = @{}
$script:HelpWidth = 520

# Adds a row: a label and a control, or with no label a control under the controls column, or
# with -Span one control across both columns.
function Add-Row([Windows.Forms.Control] $Left, [Windows.Forms.Control] $Right, [switch] $Span) {
    $row = $grid.RowCount
    $grid.RowCount = $row + 1
    [void]$grid.RowStyles.Add((New-Object Windows.Forms.RowStyle('AutoSize')))
    if ($Left) { $grid.Controls.Add($Left, 0, $row) }
    if ($Span) {
        $grid.Controls.Add($Right, 0, $row)
        $grid.SetColumnSpan($Right, 2)
    }
    else {
        $grid.Controls.Add($Right, 1, $row)
    }
}

function New-Label([string] $Text, [switch] $Help, [switch] $Bold) {
    $label = New-Object Windows.Forms.Label
    $label.Text = $Text
    $label.AutoSize = $true
    $label.MaximumSize = New-Object Drawing.Size($script:HelpWidth, 0)
    $label.Margin = New-Object Windows.Forms.Padding(3, 6, 12, 3)
    if ($Help) {
        $label.ForeColor = [Drawing.SystemColors]::GrayText
        $label.Margin = New-Object Windows.Forms.Padding(3, 0, 3, 8)
    }
    if ($Bold) { $label.Font = New-Object Drawing.Font($form.Font, [Drawing.FontStyle]::Bold) }
    $label
}

function Add-Heading([string] $Text) {
    $label = New-Label $Text -Bold
    $label.Margin = New-Object Windows.Forms.Padding(3, 14, 3, 4)
    Add-Row $null $label -Span
}

function Add-TextField([string] $Key, [string] $Label, [string] $Help, [switch] $Multiline, [int] $Width = 360) {
    $box = New-Object Windows.Forms.TextBox
    $box.Width = $Width
    if ($Multiline) {
        $box.Multiline = $true
        $box.AcceptsReturn = $true
        $box.ScrollBars = 'Vertical'
        $box.Height = 70
        $box.Width = 480
    }
    $box.Margin = New-Object Windows.Forms.Padding(3, 3, 24, 3)
    Add-Row (New-Label $Label) $box
    if ($Help) { Add-Row $null (New-Label $Help -Help) }
    $script:Inputs[$Key] = $box
    $box
}

function Add-LanguageField([string] $Key, [string] $Label, [string] $Help, [switch] $AllowEmpty) {
    $combo = New-Object Windows.Forms.ComboBox
    $combo.DropDownStyle = 'DropDownList'
    $combo.Width = 200
    if ($AllowEmpty) { [void]$combo.Items.Add('') }
    foreach ($language in (Get-NbnpLanguages)) { [void]$combo.Items.Add($language) }
    $combo.Margin = New-Object Windows.Forms.Padding(3, 3, 24, 3)
    Add-Row (New-Label $Label) $combo
    if ($Help) { Add-Row $null (New-Label $Help -Help) }
    $script:Inputs[$Key] = $combo
    $combo
}

function Set-Input([string] $Key, [string] $Text) {
    $control = $script:Inputs[$Key]
    if ($control -is [Windows.Forms.ComboBox]) {
        if ($Text -and -not $control.Items.Contains($Text)) { [void]$control.Items.Add($Text) }
        $control.SelectedItem = $Text
    }
    else {
        $control.Text = $Text -replace "(?<!`r)`n", "`r`n"
    }
}

function Get-InputText([string] $Key) {
    $control = $script:Inputs[$Key]
    if ($control -is [Windows.Forms.ComboBox]) { [string]$control.SelectedItem } else { $control.Text }
}

# Where the file goes.
$target = New-Object Windows.Forms.TextBox
$target.ReadOnly = $true
$target.Width = 480
$target.Text = $file.Path
$target.Margin = New-Object Windows.Forms.Padding(3, 3, 24, 3)
Add-Row (New-Label 'File') $target
Add-Row $null (New-Label $(if ($file.Exists) { 'Editing the existing file.' } else { 'A new file will be created.' }) -Help)

if ($Kind -eq 'Title') {
    Add-TextField 'title_id' 'Title ID' ("The Digital Title ID on the site: in the title's address, " +
        'https://newspapers.lib.unb.ca/serials/browse/48, it is 48.') -Width 120 | Out-Null
    Add-TextField 'title' 'Title' 'The exact, complete title from the masthead.' | Out-Null
    Add-LanguageField 'language' 'Language' 'eng for English, fre for French.' | Out-Null
    $media = Add-TextField 'media' 'Media' 'The source the scans were made from.' -Width 120
    $media.ReadOnly = $true

    Set-Input 'media' 'print'
    Set-Input 'language' 'eng'
}
else {
    # What the issue inherits.
    Add-Heading 'From the title'
    if ($script:TitleFile) {
        $v = $script:TitleFile.Values
        $describe = { param($key) if ($v.Contains($key)) { $v[$key].Text } else { '(not set)' } }
        $inherited = "Title: $(& $describe 'title')`nLanguage: $(& $describe 'language')    " +
            "Media: $(& $describe 'media')    Title ID: $(& $describe 'title_id')`n$($script:TitleFilePath)"
        Add-Row $null (New-Label $inherited) -Span
    }
    else {
        $none = New-Label ('No title.toml was found in the folders above this one, so this issue must give ' +
            'its title, language and media below. Better: create the title.toml in the title''s folder first.')
        $none.ForeColor = [Drawing.Color]::DarkRed
        Add-Row $null $none -Span
    }

    Add-Heading 'This issue'
    $supplement = New-Object Windows.Forms.CheckBox
    $supplement.Text = 'This is a supplement'
    $supplement.AutoSize = $true
    Add-Row $null $supplement -Span
    $script:ParentIssue = Find-NbnpParentIssue -IssueFolder $Path
    $supplementHelp = 'Date, volume and issue are those of the issue the supplement came with.'
    if ($script:ParentIssue -and (Test-Path -LiteralPath (Join-Path $script:ParentIssue 'issue.toml'))) {
        $supplementHelp += ' Ticking the box copies them from ' + (Split-Path -Leaf $script:ParentIssue) + '.'
    }
    Add-Row $null (New-Label $supplementHelp -Help) -Span

    $date = Add-TextField 'date' 'Date' ('YYYY-MM-DD, as printed, such as 1909-01-29. If the day is not printed ' +
        '(a monthly) or you are unsure of it, use the 1st.') -Width 120
    $weekday = New-Label ''
    $weekday.ForeColor = [Drawing.SystemColors]::GrayText
    Add-Row $null $weekday
    $date.Add_TextChanged({
            $parsed = [datetime]::MinValue
            $weekday.Text = if ([datetime]::TryParseExact($date.Text.Trim(), 'yyyy-MM-dd',
                    [Globalization.CultureInfo]::InvariantCulture, 'None', [ref]$parsed)) {
                $parsed.ToString('dddd, MMMM d, yyyy', [Globalization.CultureInfo]::GetCultureInfo('en-CA'))
            }
            else { '' }
        })

    Add-TextField 'volume' 'Volume' ('Exactly as printed, even if it is wrong; note a wrong number in Errata. ' +
        'No zero padding: 5, not 05.') -Width 120 | Out-Null
    Add-TextField 'issue' 'Issue' ('Exactly as printed, even if it is wrong; note a wrong number in Errata. ' +
        'No zero padding: 5, not 05.') -Width 120 | Out-Null
    Add-TextField 'edition' 'Edition' 'Optional. The edition statement as printed, such as Evening.' | Out-Null
    $supplementTitle = Add-TextField 'supplement_title' 'Supplement title' 'The supplement''s title as printed, if it has one.'
    Add-TextField 'missing_pages' 'Missing pages' ('Optional. Page numbers separated by commas, or ranges: ' +
        '3, 6, 7 or 4-8. Numbers, dashes and commas only; no zero padding.') -Width 200 | Out-Null
    $errata = Add-TextField 'errata' 'Errata' ('Optional. Short and clear to everyone. For any problem with the ' +
        'printed issue numbering, use "Incorrect issue numbering".') -Multiline
    $insert = New-Object Windows.Forms.Button
    $insert.Text = 'Insert "Incorrect issue numbering"'
    $insert.AutoSize = $true
    Add-Row $null $insert
    $insert.Add_Click({
            $phrase = 'Incorrect issue numbering'
            if ($errata.Text -notmatch [regex]::Escape($phrase)) {
                $errata.Text = if ($errata.Text.Trim()) { $errata.Text.TrimEnd() + "`r`n" + $phrase } else { $phrase }
            }
        })

    Add-Heading 'Different from the title (usually left empty)'
    Add-Row $null (New-Label ('Fill these only when this issue differs from the title above, for example when ' +
        'its masthead prints a different title. Empty fields use the title''s values.') -Help) -Span
    Add-TextField 'title' 'Title' 'The exact, complete title from this issue''s masthead.' | Out-Null
    Add-LanguageField 'language' 'Language' '' -AllowEmpty | Out-Null
    Add-TextField 'media' 'Media' '' -Width 120 | Out-Null
    Add-TextField 'title_id' 'Title ID' 'Only if this issue belongs to a different Digital Title.' -Width 120 | Out-Null
    if (-not $script:TitleFile) { Set-Input 'media' 'print'; Set-Input 'language' 'eng' }

    $supplementTitle.Enabled = $false
    $supplement.Add_CheckedChanged({
            $supplementTitle.Enabled = $supplement.Checked
            if (-not $supplement.Checked -or -not $script:ParentIssue) { return }
            $parent = Read-NbnpMetadataFile -Folder $script:ParentIssue -Kind Issue
            if (-not $parent.Exists -or -not $parent.Supported) { return }
            foreach ($key in @('date', 'volume', 'issue')) {
                if ((Get-InputText $key).Trim() -eq '' -and $parent.Values.Contains($key)) {
                    Set-Input $key $parent.Values[$key].Text
                }
            }
        })
}

# Fill in the existing values.
foreach ($key in @($file.Values.Keys)) {
    if ($script:Inputs.ContainsKey($key)) { Set-Input $key $file.Values[$key].Text }
}
if ($Kind -eq 'Issue' -and $file.Values.Contains('supplement_title')) { $supplement.Checked = $true }

# Messages and buttons.
$status = New-Object Windows.Forms.Label
$status.AutoSize = $true
$status.MaximumSize = New-Object Drawing.Size(680, 0)
$status.ForeColor = [Drawing.Color]::DarkRed
$status.Margin = New-Object Windows.Forms.Padding(3, 12, 3, 6)
Add-Row $null $status -Span

$buttons = New-Object Windows.Forms.FlowLayoutPanel
$buttons.FlowDirection = 'RightToLeft'
$buttons.AutoSize = $true
$buttons.Dock = 'Fill'
$cancel = New-Object Windows.Forms.Button
$cancel.Text = 'Cancel'
$cancel.DialogResult = 'Cancel'
$save = New-Object Windows.Forms.Button
$save.Text = 'Save'
$buttons.Controls.AddRange(@($cancel, $save))
Add-Row $null $buttons -Span
$form.AcceptButton = $null   # Enter adds lines in Errata; Save is clicked.
$form.CancelButton = $cancel

$form.Controls.Add($grid)

#endregion

#region Checking and saving

function Get-FormValues {
    $values = [ordered]@{}
    foreach ($key in $script:Inputs.Keys) {
        $text = Get-InputText $key
        if ($key -eq 'supplement_title' -and -not $supplement.Checked) { continue }
        $valueKind = if ($key -eq 'date' -or $key -eq 'title_id') { 'bare' } else { 'string' }
        # An empty Title ID override is left out, not written as nothing.
        if ($script:FileKind -eq 'Issue' -and $key -eq 'title_id' -and $text.Trim() -eq '') { continue }
        $values[$key] = New-NbnpValue $text $valueKind
    }
    $values
}

function Show-Findings($Findings, [string] $Heading = '') {
    $errorProvider.Clear()
    $lines = @()
    if ($Heading) { $lines += $Heading }
    foreach ($finding in $Findings) {
        $prefix = if ($finding.Severity -eq 'Error') { '' } else { 'Check: ' }
        $lines += "$prefix$($finding.Message)"
        if ($finding.Severity -eq 'Error' -and $script:Inputs.ContainsKey($finding.Field)) {
            $control = $script:Inputs[$finding.Field]
            if ($errorProvider.GetError($control) -eq '') { $errorProvider.SetError($control, $finding.Message) }
        }
    }
    $status.Text = $lines -join "`n"
}

function Test-Form {
    Test-NbnpMetadata -Kind $Kind -Values (Get-FormValues) -Title $script:TitleFile -Folder $Path
}

# Problems in the file as it was opened, shown at once.
if ($file.Exists) {
    $opened = Test-NbnpMetadata -Kind $Kind -Values $file.Values -Title $script:TitleFile -Folder $Path
    if ($opened.Findings.Count -gt 0) {
        Show-Findings $opened.Findings ('The file as opened needs these corrections (values written with the wrong ' +
            'quoting are corrected on Save):')
    }
}

$script:Saved = $false
$script:Initial = (Get-FormValues | ConvertTo-Json -Compress)

$save.Add_Click({
        $checked = Test-Form
        Show-Findings $checked.Findings
        $errors = @($checked.Findings | Where-Object Severity -EQ 'Error')
        if ($errors.Count -gt 0) {
            [void](Show-Message "Please correct the $(if ($errors.Count -eq 1) { 'value' } else { "$($errors.Count) values" }) marked in red, then save again." 'Warning')
            return
        }
        $warnings = @($checked.Findings | Where-Object Severity -EQ 'Warning')
        if ($warnings.Count -gt 0) {
            $answer = Show-Message (($warnings.Message -join "`n") + "`n`nSave anyway?") 'Question' 'YesNo'
            if ($answer -ne 'Yes') { return }
        }
        try {
            $text = ConvertTo-NbnpToml -Kind $Kind -Values $checked.Values -Header $file.Header
            Save-NbnpMetadataFile -Path $file.Path -Text $text -ExpectedHash $file.Hash
        }
        catch {
            [void](Show-Message "Nothing was saved.`n`n$($_.Exception.Message)" 'Error')
            return
        }
        $script:Saved = $true
        $backup = if ($file.Exists) { "`nThe previous version is kept as $(Split-Path -Leaf $file.Path).bak." } else { '' }
        [void](Show-Message "Saved $($file.Path).$backup")
        $form.Close()
    })

$form.Add_FormClosing({
        param($formSender, $e)
        if ($script:Saved) { return }
        if ((Get-FormValues | ConvertTo-Json -Compress) -ne $script:Initial) {
            $answer = Show-Message 'Close without saving your changes?' 'Question' 'YesNo'
            if ($answer -ne 'Yes') { $e.Cancel = $true }
        }
    })

#endregion

[void]$form.ShowDialog()
