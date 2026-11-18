#Requires -Version 5.1
<#
.SYNOPSIS
    Opens a form to create or edit the title.toml or issue.toml in a folder.

.DESCRIPTION
    Started from the Explorer right-click menu (Install-ContextMenu.ps1). The folder is only
    ever used as a path, never run as code. Nothing is written until Save, and Save refuses
    values nbnp-ingest would not accept.

    The issue form is laid out for runs of issues: the masthead of the first page is shown
    beside the fields, the fields follow the masthead (volume, number, date), and Save and next
    (Enter) saves and opens the next issue folder in the same window, filled from the issue
    just saved.

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
$script:Bounds = $null

$script:Green = [Drawing.Color]::FromArgb(0, 110, 40)
$script:Red = [Drawing.Color]::DarkRed

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

#endregion

#region The masthead

function Get-MastheadImage([string] $ImagePath, [int] $Width) {
    # The top of the first page, decoded at display size: Windows' imaging decoder scales a
    # JPEG while reading it, so a 13 MB scan does not have to be decoded in full.
    Add-Type -AssemblyName PresentationCore, WindowsBase
    $stream = [IO.File]::OpenRead($ImagePath)
    try {
        $decoded = New-Object Windows.Media.Imaging.BitmapImage
        $decoded.BeginInit()
        $decoded.CacheOption = [Windows.Media.Imaging.BitmapCacheOption]::OnLoad
        $decoded.StreamSource = $stream
        $decoded.DecodePixelWidth = $Width
        $decoded.EndInit()
        $decoded.Freeze()
    }
    finally { $stream.Dispose() }
    $height = [Math]::Max(1, [int]($decoded.PixelHeight * 0.3))
    $crop = New-Object Windows.Media.Imaging.CroppedBitmap($decoded, (New-Object Windows.Int32Rect(0, 0, $decoded.PixelWidth, $height)))
    $encoder = New-Object Windows.Media.Imaging.BmpBitmapEncoder
    $encoder.Frames.Add([Windows.Media.Imaging.BitmapFrame]::Create($crop))
    $memory = New-Object IO.MemoryStream
    try {
        $encoder.Save($memory)
        $memory.Position = 0
        $image = [Drawing.Image]::FromStream($memory)
        try { New-Object Drawing.Bitmap($image) } finally { $image.Dispose() }
    }
    finally { $memory.Dispose() }
}

#endregion

#region Layout helpers

function New-Grid {
    $grid = New-Object Windows.Forms.TableLayoutPanel
    $grid.ColumnCount = 2
    [void]$grid.ColumnStyles.Add((New-Object Windows.Forms.ColumnStyle('AutoSize')))
    [void]$grid.ColumnStyles.Add((New-Object Windows.Forms.ColumnStyle('Percent', 100)))
    $grid.Dock = 'Fill'
    $grid.AutoSize = $true
    $grid.Margin = New-Object Windows.Forms.Padding(0, 0, 12, 0)
    $grid
}

# Adds a row to the current grid: a label and a control, or with no label a control under the
# controls column, or with -Span one control across both columns.
function Add-Row([Windows.Forms.Control] $Left, [Windows.Forms.Control] $Right, [switch] $Span) {
    $grid = $script:Grid
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

function New-Label([string] $Text, [switch] $Hint, [switch] $Bold) {
    $label = New-Object Windows.Forms.Label
    $label.Text = $Text
    $label.AutoSize = $true
    $label.MaximumSize = New-Object Drawing.Size(460, 0)
    $label.Margin = New-Object Windows.Forms.Padding(3, 6, 12, 3)
    if ($Hint) {
        $label.ForeColor = [Drawing.SystemColors]::GrayText
        $label.Margin = New-Object Windows.Forms.Padding(3, 0, 3, 6)
    }
    if ($Bold) { $label.Font = New-Object Drawing.Font($script:Form.Font, [Drawing.FontStyle]::Bold) }
    $label
}

function Add-Heading([string] $Text) {
    $label = New-Label $Text -Bold
    $label.Margin = New-Object Windows.Forms.Padding(3, 10, 3, 4)
    Add-Row $null $label -Span
}

# The next position in the Tab order.
function Set-TabOrder([Windows.Forms.Control] $Control) {
    $Control.TabIndex = $script:NextTab
    $script:NextTab++
}

# A text field with its label, a short hint under it, and the full guidance as a tooltip.
function Add-TextField([string] $Key, [string] $Label, [string] $Hint, [string] $Tip, [int] $Width = 300, [switch] $Multiline) {
    $box = New-Object Windows.Forms.TextBox
    $box.Width = $Width
    if ($Multiline) {
        $box.Multiline = $true
        $box.AcceptsReturn = $true
        $box.ScrollBars = 'Vertical'
        $box.Height = 70
    }
    $box.Margin = New-Object Windows.Forms.Padding(3, 3, 24, 3)
    $labelControl = New-Label $Label
    Add-Row $labelControl $box
    if ($Hint) { Add-Row $null (New-Label $Hint -Hint) }
    if ($Tip) {
        $script:Tips.SetToolTip($box, $Tip)
        $script:Tips.SetToolTip($labelControl, $Tip)
    }
    Set-TabOrder $box
    $box.Add_Leave({ param($source) [void](Update-Findings -Touched $source.Tag) })
    $box.Tag = $Key
    $script:Inputs[$Key] = $box
    $box
}

function Add-LanguageField([string] $Key, [string] $Label, [string] $Tip, [switch] $AllowEmpty) {
    $combo = New-Object Windows.Forms.ComboBox
    $combo.DropDownStyle = 'DropDownList'
    $combo.Width = 120
    if ($AllowEmpty) { [void]$combo.Items.Add('') }
    foreach ($language in (Get-NbnpLanguages)) { [void]$combo.Items.Add($language) }
    $combo.Margin = New-Object Windows.Forms.Padding(3, 3, 24, 3)
    $labelControl = New-Label $Label
    Add-Row $labelControl $combo
    if ($Tip) { $script:Tips.SetToolTip($combo, $Tip); $script:Tips.SetToolTip($labelControl, $Tip) }
    Set-TabOrder $combo
    $combo.Tag = $Key
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

#endregion

#region Checking and saving

function Get-FormValues {
    $values = [ordered]@{}
    foreach ($key in $script:Inputs.Keys) {
        if ($key -eq 'supplement_title' -and -not $script:Supplement.Checked) { continue }
        $valueKind = if ($key -eq 'date' -or $key -eq 'title_id') { 'bare' } else { 'string' }
        $values[$key] = New-NbnpValue (Get-InputText $key) $valueKind
    }
    $values
}

function Test-Form {
    $checked = Test-NbnpMetadata -Kind $script:FileKind -Values (Get-FormValues) -Title $script:TitleFile -Folder $script:Folder
    $findings = @($checked.Findings)
    if ($script:FileKind -eq 'Issue') {
        $repeat = Test-NbnpRepeatOfLast -Values $checked.Values -Folder $script:Folder -Last $script:LastIssue
        if ($repeat) { $findings += $repeat }
    }
    [pscustomobject]@{ Values = $checked.Values; Findings = $findings }
}

# Shows findings: all of them, or, while typing, only those about fields already visited.
function Update-Findings([string] $Touched = '', [switch] $All) {
    if ($Touched) {
        $script:Touched[$Touched] = $true
        # A loosely typed date is rewritten as YYYY-MM-DD as soon as the field is left.
        if ($Touched -eq 'date') {
            $typed = Get-InputText 'date'
            $rewritten = ConvertTo-NbnpDate $typed
            if ($rewritten -ne $typed) { Set-Input 'date' $rewritten }
        }
    }
    $findings = @((Test-Form).Findings | Where-Object { $All -or $script:Touched.ContainsKey($_.Field) })
    $script:Errors.Clear()
    $lines = @()
    foreach ($finding in $findings) {
        $prefix = if ($finding.Severity -eq 'Error') { '' } else { 'Check: ' }
        $lines += "$prefix$($finding.Message)"
        if ($finding.Severity -eq 'Error' -and $script:Inputs.Contains($finding.Field)) {
            $control = $script:Inputs[$finding.Field]
            if ($script:Errors.GetError($control) -eq '') { $script:Errors.SetError($control, $finding.Message) }
        }
    }
    $script:Status.ForeColor = $script:Red
    $script:Status.Text = $lines -join "`n"
    $findings
}

function Invoke-Save([switch] $Next) {
    $checked = Test-Form
    $findings = Update-Findings -All
    $errors = @($findings | Where-Object Severity -EQ 'Error')
    if ($errors.Count -gt 0) {
        $first = $script:Inputs[$errors[0].Field]
        if ($first) { [void]$first.Focus() }
        return
    }
    $warnings = @($findings | Where-Object Severity -EQ 'Warning')
    if ($warnings.Count -gt 0) {
        $answer = Show-Message (($warnings.Message -join "`n`n") + "`n`nSave anyway?") 'Question' 'YesNo'
        if ($answer -ne 'Yes') { return }
    }
    try {
        $text = ConvertTo-NbnpToml -Kind $script:FileKind -Values $checked.Values -Header $script:File.Header
        Save-NbnpMetadataFile -Path $script:File.Path -Text $text -ExpectedHash $script:File.Hash
        if ($script:FileKind -eq 'Issue') { Save-NbnpLastIssue -Values $checked.Values -Folder $script:Folder }
    }
    catch {
        [void](Show-Message "Nothing was saved.`n`n$($_.Exception.Message)" 'Error')
        return
    }
    $name = Split-Path -Leaf $script:Folder
    $script:Result = @{ Next = $null; Status = '' }
    if ($Next -and $script:NextFolder) {
        $script:Result = @{ Next = $script:NextFolder; Status = "Saved $name. Now $(Split-Path -Leaf $script:NextFolder)." }
    }
    $script:Saved = $true
    $script:Form.Close()
}

#endregion

#region The form

# Shows the form for one folder. Returns the next folder to show and the message to show
# there, or $null when the user is done.
function Show-Editor([string] $Folder, [string] $Arrived = '') {
    $script:Folder = $Folder
    $script:Inputs = [ordered]@{}
    $script:Touched = @{}
    $script:NextTab = 0
    $script:Saved = $false
    $script:Result = $null
    $script:Supplement = $null
    $script:TitleFile = $null
    $script:LastIssue = $null
    $script:NextFolder = $null
    $script:ParentIssue = $null
    $preview = $null
    $isIssue = $script:FileKind -eq 'Issue'

    $legacy = Join-Path $Folder 'metadata.php'
    if ($isIssue -and (Test-Path -LiteralPath $legacy -PathType Leaf)) {
        Show-ReadOnly ("This issue's metadata is in the older metadata.php file, so it cannot be edited here. " +
            'Edit metadata.php as before; it is shown below.') $legacy ([IO.File]::ReadAllText($legacy))
        return $null
    }
    $script:File = Read-NbnpMetadataFile -Folder $Folder -Kind $script:FileKind
    if (-not $script:File.Supported) {
        Show-ReadOnly ("This file cannot be edited here, so that nothing in it is lost: $($script:File.Reason). " +
            'Ask whoever maintains the newspaper metadata to look at it.') $script:File.Path $script:File.Text
        return $null
    }
    if ($isIssue) {
        $titlePath = Find-NbnpTitleFile -IssueFolder $Folder
        if ($titlePath) {
            $title = Read-NbnpMetadataFile -Folder (Split-Path -Parent $titlePath) -Kind Title
            if ($title.Supported) { $script:TitleFile = $title }
        }
        $script:LastIssue = Get-NbnpLastIssue
        $script:NextFolder = Find-NbnpNextIssueFolder -IssueFolder $Folder
    }

    $form = New-Object Windows.Forms.Form
    $script:Form = $form
    $form.Text = "$($script:AppTitle): $(Split-Path -Leaf $Folder)"
    $form.Font = New-Object Drawing.Font('Segoe UI', 9)
    $form.AutoScaleMode = 'Dpi'
    $form.AutoScroll = $true
    $form.KeyPreview = $true
    $form.MinimumSize = New-Object Drawing.Size(600, 400)
    if ($script:Bounds) {
        $form.StartPosition = 'Manual'
        $form.Bounds = $script:Bounds
    }
    else {
        $form.StartPosition = 'CenterScreen'
        $form.ClientSize = if ($isIssue) { New-Object Drawing.Size(1100, 640) } else { New-Object Drawing.Size(640, 420) }
    }

    $script:Errors = New-Object Windows.Forms.ErrorProvider
    $script:Errors.BlinkStyle = 'NeverBlink'
    $script:Tips = New-Object Windows.Forms.ToolTip
    $script:Tips.AutoPopDelay = 20000

    $columns = if ($isIssue) { 2 } else { 1 }
    $outer = New-Object Windows.Forms.TableLayoutPanel
    $outer.ColumnCount = $columns
    for ($i = 0; $i -lt $columns; $i++) {
        [void]$outer.ColumnStyles.Add((New-Object Windows.Forms.ColumnStyle('Percent', (100 / $columns))))
    }
    $outer.Dock = 'Top'
    $outer.AutoSize = $true
    $outer.Padding = New-Object Windows.Forms.Padding(12)
    $addOuter = {
        param([Windows.Forms.Control[]] $Controls, [switch] $Span)
        $row = $outer.RowCount
        $outer.RowCount = $row + 1
        [void]$outer.RowStyles.Add((New-Object Windows.Forms.RowStyle('AutoSize')))
        for ($i = 0; $i -lt $Controls.Count; $i++) { $outer.Controls.Add($Controls[$i], $i, $row) }
        if ($Span) { $outer.SetColumnSpan($Controls[0], $columns) }
    }

    # The file, and what happened on the way here.
    $header = New-Grid
    $script:Grid = $header
    $target = New-Object Windows.Forms.TextBox
    $target.ReadOnly = $true
    $target.TabStop = $false
    $target.Width = if ($isIssue) { 900 } else { 480 }
    $target.Text = $script:File.Path
    Add-Row (New-Label 'File') $target
    Add-Row $null (New-Label $(if ($script:File.Exists) { 'Editing the existing file.' } else { 'A new file will be created.' }) -Hint)
    if ($Arrived) {
        $arrivedLabel = New-Label $Arrived
        $arrivedLabel.ForeColor = $script:Green
        Add-Row $null $arrivedLabel -Span
    }

    $left = New-Grid
    $right = if ($isIssue) { New-Grid } else { $null }
    & $addOuter @($header) -Span
    & $addOuter @(@($left, $right) | Where-Object { $null -ne $_ })
    $left.TabIndex = 0
    if ($right) { $right.TabIndex = 1 }
    $script:Grid = $left

    if (-not $isIssue) {
        Add-TextField 'title_id' 'Title ID' 'Leave empty if not known yet.' ("The Digital Title ID on the site: in " +
            "https://newspapers.lib.unb.ca/serials/browse/48 it is 48. The title's issues cannot be imported " +
            'until it is filled in.') -Width 120 | Out-Null
        Add-TextField 'title' 'Title' 'Exactly as on the masthead.' 'The exact, complete title from the masthead.' | Out-Null
        Add-LanguageField 'language' 'Language' 'eng for English, fre for French.' | Out-Null
        $media = Add-TextField 'media' 'Media' '' 'The source the scans were made from.' -Width 120
        $media.ReadOnly = $true
        Set-Input 'media' 'print'
        Set-Input 'language' 'eng'
    }
    else {
        Add-Heading 'This issue'
        $fill = New-Object Windows.Forms.Button
        $fill.Text = '&Fill from last saved issue'
        $fill.AutoSize = $true
        $fill.TabStop = $false
        $fill.Enabled = $null -ne $script:LastIssue
        Add-Row $null $fill -Span
        $lastHint = if ($script:LastIssue) {
            "Alt+F. Last saved: $(Split-Path -Leaf $script:LastIssue.Folder), $($script:LastIssue.Saved.ToString('yyyy-MM-dd HH:mm')). " +
            'Copies all but missing pages and errata.'
        }
        else { 'Available once you have saved an issue.' }
        Add-Row $null (New-Label $lastHint -Hint) -Span

        $asPrinted = 'Exactly as printed, even if it is wrong; note a wrong number in Errata. No zero padding: 5, not 05.'
        Add-TextField 'volume' 'Volume' 'As printed; 5, not 05.' $asPrinted -Width 120 | Out-Null
        Add-TextField 'issue' 'Issue (No.)' 'As printed; 5, not 05.' $asPrinted -Width 120 | Out-Null
        $date = Add-TextField 'date' 'Date' 'e.g. 1902-03-28 or March 28, 1902. Day not printed: use the 1st.' (
            'The date as printed. Written YYYY-MM-DD; 1902-3-28, March 28, 1902 and 28 March 1902 are also ' +
            'accepted and rewritten. If the day is not printed (a monthly) or you are unsure of it, use the 1st.') -Width 160
        $weekday = New-Label '' -Hint
        Add-Row $null $weekday
        $date.Add_TextChanged({
                $parsed = [datetime]::MinValue
                $normal = ConvertTo-NbnpDate $script:Inputs['date'].Text
                $weekday.Text = if ([datetime]::TryParseExact($normal, 'yyyy-MM-dd',
                        [Globalization.CultureInfo]::InvariantCulture, 'None', [ref]$parsed)) {
                    $parsed.ToString('dddd, MMMM d, yyyy', [Globalization.CultureInfo]::GetCultureInfo('en-CA'))
                }
                else { '' }
            })
        Add-TextField 'edition' 'Edition' 'Optional.' 'The edition statement as printed, such as Evening.' | Out-Null
        Add-TextField 'missing_pages' 'Missing pages' 'Optional; e.g. 3, 6-8.' (
            'Page numbers separated by commas, or ranges: 3, 6, 7 or 4-8. Numbers, dashes and commas only; ' +
            'no zero padding.') -Width 160 | Out-Null
        Add-TextField 'errata' 'Errata' 'Optional. Short and clear to everyone.' (
            'For any problem with the printed issue numbering, use "Incorrect issue numbering".') -Multiline -Width 300 | Out-Null
        $phrase = New-Object Windows.Forms.Button
        $phrase.Text = 'Insert "Incorrect issue numbering"'
        $phrase.AutoSize = $true
        $phrase.TabStop = $false
        Add-Row $null $phrase
        $phrase.Add_Click({
                $text = 'Incorrect issue numbering'
                $box = $script:Inputs['errata']
                if ($box.Text -notmatch [regex]::Escape($text)) {
                    $box.Text = if ($box.Text.Trim()) { $box.Text.TrimEnd() + "`r`n" + $text } else { $text }
                }
            })

        $script:Supplement = New-Object Windows.Forms.CheckBox
        $script:Supplement.Text = 'This is a supplement'
        $script:Supplement.AutoSize = $true
        $script:Supplement.Margin = New-Object Windows.Forms.Padding(3, 10, 3, 3)
        Add-Row $null $script:Supplement -Span
        Set-TabOrder $script:Supplement
        $supplementTitle = Add-TextField 'supplement_title' 'Supplement title' 'As printed, if it has one.' (
            'Date, volume and issue are those of the issue the supplement came with.')
        $supplementTitle.Enabled = $false
        $script:ParentIssue = Find-NbnpParentIssue -IssueFolder $Folder
        $script:Supplement.Add_CheckedChanged({
                $script:Inputs['supplement_title'].Enabled = $script:Supplement.Checked
                if (-not $script:Supplement.Checked -or -not $script:ParentIssue) { return }
                $parent = Read-NbnpMetadataFile -Folder $script:ParentIssue -Kind Issue
                if (-not $parent.Exists -or -not $parent.Supported) { return }
                foreach ($key in @('date', 'volume', 'issue')) {
                    if ((Get-InputText $key).Trim() -eq '' -and $parent.Values.Contains($key)) {
                        Set-Input $key $parent.Values[$key].Text
                    }
                }
            })

        # Right: the masthead, then the title.
        $script:Grid = $right
        Add-Heading 'Masthead'
        $script:FirstPage = Get-NbnpFirstPage -Folder $Folder
        $preview = New-Object Windows.Forms.PictureBox
        $preview.SizeMode = 'Zoom'
        $preview.Width = 500
        $preview.Height = 200
        $preview.Cursor = [Windows.Forms.Cursors]::Hand
        $preview.BorderStyle = 'FixedSingle'
        Add-Row $null $preview -Span
        $previewHint = New-Label $(if ($script:FirstPage) { "Loading $(Split-Path -Leaf $script:FirstPage)..." } else { 'No page images in this folder.' }) -Hint
        Add-Row $null $previewHint -Span
        if ($script:FirstPage) {
            $script:Tips.SetToolTip($preview, 'Click to open the whole page.')
            $preview.Add_Click({ Start-Process -FilePath $script:FirstPage })
            $form.Add_Shown({
                    try {
                        $image = Get-MastheadImage $script:FirstPage 1000
                        $preview.Height = [int]($preview.Width * $image.Height / $image.Width)
                        $preview.Image = $image
                        $previewHint.Text = "Top of $(Split-Path -Leaf $script:FirstPage). Click to open the whole page."
                    }
                    catch {
                        $previewHint.Text = "Could not show $(Split-Path -Leaf $script:FirstPage): $($_.Exception.Message)"
                    }
                })
        }

        Add-Heading 'From the title'
        if ($script:TitleFile) {
            $v = $script:TitleFile.Values
            $describe = { param($key) if ($v.Contains($key)) { $v[$key].Text } else { '(not set)' } }
            Add-Row $null (New-Label ("$(& $describe 'title') - $(& $describe 'language'), $(& $describe 'media'), " +
                    "title ID $(& $describe 'title_id')")) -Span
        }
        else {
            $none = New-Label ('No title.toml above this folder: fill in the title, language and media below, ' +
                'or better, create the title.toml in the title''s folder first.')
            $none.ForeColor = $script:Red
            Add-Row $null $none -Span
        }
        $differs = New-Object Windows.Forms.CheckBox
        $differs.Text = 'Different from the title...'
        $differs.AutoSize = $true
        $differs.TabStop = $false
        Add-Row $null $differs -Span
        $overrides = New-Grid
        $overrides.Visible = $false
        Add-Row $null $overrides -Span
        $script:Grid = $overrides
        Add-TextField 'title' 'Title' '' 'The exact, complete title from this issue''s masthead, when it differs from the title''s.' | Out-Null
        Add-LanguageField 'language' 'Language' 'Only when this issue''s language differs from the title''s.' -AllowEmpty | Out-Null
        Add-TextField 'media' 'Media' '' 'The source the scans were made from.' -Width 120 | Out-Null
        Add-TextField 'title_id' 'Title ID' '' 'Only if this issue belongs to a different Digital Title.' -Width 120 | Out-Null
        if (-not $script:TitleFile) { Set-Input 'media' 'print'; Set-Input 'language' 'eng' }
        $differs.Add_CheckedChanged({ $overrides.Visible = $differs.Checked })

        $fill.Add_Click({
                $last = $script:LastIssue.Values
                # Every field but missing pages and errata takes the last issue's value, or none.
                $carried = @($script:Inputs.Keys | Where-Object { $_ -ne 'missing_pages' -and $_ -ne 'errata' })
                $wanted = @{}
                foreach ($key in $carried) { $wanted[$key] = if ($last.Contains($key)) { $last[$key].Text } else { '' } }
                $replaced = @($carried | Where-Object {
                        (Get-InputText $_).Trim() -ne '' -and (Get-InputText $_).Trim() -ne $wanted[$_] })
                if ($replaced.Count -gt 0) {
                    $answer = Show-Message 'Replace what is already in the form with the last issue''s values?' 'Question' 'YesNo'
                    if ($answer -ne 'Yes') { return }
                }
                Fill-FromLast
            })
    }

    # Messages and buttons, across the whole width.
    $script:Status = New-Object Windows.Forms.Label
    $script:Status.AutoSize = $true
    $script:Status.MaximumSize = New-Object Drawing.Size(1000, 0)
    $script:Status.Margin = New-Object Windows.Forms.Padding(3, 10, 3, 6)
    & $addOuter @($script:Status) -Span

    $buttons = New-Object Windows.Forms.FlowLayoutPanel
    $buttons.FlowDirection = 'RightToLeft'
    $buttons.AutoSize = $true
    $buttons.Dock = 'Fill'
    $cancel = New-Object Windows.Forms.Button
    $cancel.Text = 'Cancel'
    $cancel.AutoSize = $true
    $cancel.DialogResult = 'Cancel'
    $saveClose = New-Object Windows.Forms.Button
    $saveClose.Text = if ($isIssue) { 'Save and close' } else { 'Save' }
    $saveClose.AutoSize = $true
    $saveClose.Add_Click({ Invoke-Save })
    $controls = @($cancel, $saveClose)
    $saveNext = $null
    if ($isIssue) {
        $saveNext = New-Object Windows.Forms.Button
        $saveNext.Text = 'Save and next'
        $saveNext.AutoSize = $true
        $saveNext.Font = New-Object Drawing.Font($form.Font, [Drawing.FontStyle]::Bold)
        $saveNext.Add_Click({ Invoke-Save -Next })
        if ($script:NextFolder) {
            $script:Tips.SetToolTip($saveNext, "Enter or Ctrl+S. Saves, then opens $(Split-Path -Leaf $script:NextFolder).")
        }
        else {
            $saveNext.Enabled = $false
            $script:Tips.SetToolTip($saveNext, 'There is no issue folder after this one.')
        }
        $controls += $saveNext
    }
    $buttons.Controls.AddRange([Windows.Forms.Control[]]$controls)
    & $addOuter @($buttons) -Span
    $buttons.TabIndex = 2
    $default = if ($saveNext -and $saveNext.Enabled) { $saveNext } else { $saveClose }
    # Enter saves from any one-line field; in Errata it adds a line.
    $form.AcceptButton = $default
    $form.CancelButton = $cancel
    $form.Add_KeyDown({
            param($source, $e)
            if ($e.Control -and $e.KeyCode -eq 'S') {
                $e.SuppressKeyPress = $true
                $script:Form.AcceptButton.PerformClick()
            }
        })
    $form.Controls.Add($outer)

    # Fill in the existing values, or for a new issue whatever is already known.
    foreach ($key in @($script:File.Values.Keys)) {
        if ($script:Inputs.Contains($key)) { Set-Input $key $script:File.Values[$key].Text }
    }
    $focus = if ($isIssue) { 'volume' } else { 'title_id' }
    if ($isIssue) {
        $overrideKeys = @('title', 'language', 'media', 'title_id')
        if (-not $script:TitleFile -or @($overrideKeys | Where-Object { $script:File.Values.Contains($_) }).Count -gt 0) {
            $differs.Checked = $true
        }
        if ($script:File.Values.Contains('supplement_title')) { $script:Supplement.Checked = $true }
        if (-not $script:File.Exists) {
            $parentFile = if ($script:ParentIssue) { Join-Path $script:ParentIssue 'issue.toml' } else { $null }
            if ($parentFile -and (Test-Path -LiteralPath $parentFile)) {
                # A supplement folder beside its issue: its date, volume and number are the issue's.
                $script:Supplement.Checked = $true
                $focus = 'supplement_title'
            }
            elseif ($Arrived -and $script:LastIssue) {
                # Arrived with Save and next: start from the issue just saved.
                Fill-FromLast
                $focus = 'issue'
            }
        }
    }
    $script:Initial = (Get-FormValues | ConvertTo-Json -Compress)
    if ($script:File.Exists) { [void](Update-Findings -All) }
    $script:Focus = $focus
    $form.Add_Shown({
            $box = $script:Inputs[$script:Focus]
            [void]$box.Focus()
            if ($box -is [Windows.Forms.TextBox]) { $box.SelectAll() }
        })

    $form.Add_FormClosing({
            param($source, $e)
            if ($script:Saved) { return }
            if ((Get-FormValues | ConvertTo-Json -Compress) -ne $script:Initial) {
                $answer = Show-Message 'Close without saving your changes?' 'Question' 'YesNo'
                if ($answer -ne 'Yes') { $e.Cancel = $true }
            }
        })

    [void]$form.ShowDialog()
    $script:Bounds = $form.Bounds
    if ($preview -and $preview.Image) { $preview.Image.Dispose() }
    $form.Dispose()
    $script:Result
}

# Copies the last issue saved into the form, all but missing pages and errata. A supplement's
# title carries over only into another supplement's folder, so an ordinary issue after a
# supplement is not marked as one.
function Fill-FromLast {
    $last = $script:LastIssue.Values
    $carrySupplement = $null -ne $script:ParentIssue
    foreach ($key in @($script:Inputs.Keys | Where-Object { $_ -ne 'missing_pages' -and $_ -ne 'errata' })) {
        if ($key -eq 'supplement_title' -and -not $carrySupplement) { Set-Input $key ''; continue }
        $value = if ($last.Contains($key)) { $last[$key].Text } else { '' }
        Set-Input $key $value
    }
    $script:Supplement.Checked = $carrySupplement -and $last.Contains('supplement_title')
    $box = $script:Inputs['issue']
    [void]$box.Focus()
    $box.SelectAll()
}

#endregion

# One folder after another, for as long as Save and next is used.
$folder = $Path
$arrived = ''
while ($folder) {
    $result = Show-Editor $folder $arrived
    if (-not $result -or -not $result.Next) { break }
    $folder = $result.Next
    $arrived = $result.Status
}
