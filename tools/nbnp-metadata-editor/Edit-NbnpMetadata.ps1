#Requires -Version 5.1
<#
.SYNOPSIS
    The NBNP metadata editor: one window for a title's title.toml and its issues' issue.toml.

.DESCRIPTION
    Started from the Explorer right-click menu (Install-ContextMenu.ps1) on any folder of a
    title. The folder is only ever used as a path, never run as code.

    The window has two views. The title view edits title.toml and sums up the title's issue
    folders; issues can be entered only once the title has its title.toml. The issue view edits
    one issue.toml at a time beside the issue's pages, and Save and next (Enter) moves through
    the title's issues in order. Nothing is written until a save, and a save refuses values
    nbnp-ingest would not accept.

.PARAMETER Path
    Any folder of a title: the title's own folder, a year or volume folder, or an issue folder.

.PARAMETER Kind
    Ignored. Accepted so that menu entries from earlier versions still start the editor.
#>
[CmdletBinding()]
param(
    [Parameter(Mandatory)][string] $Path,
    [string] $Kind = ''
)

Set-StrictMode -Version 2.0
$ErrorActionPreference = 'Stop'

Add-Type -AssemblyName System.Windows.Forms, System.Drawing
[Windows.Forms.Application]::EnableVisualStyles()
Import-Module (Join-Path $PSScriptRoot 'NbnpMetadata.psm1') -Force

$script:AppTitle = 'NBNP Metadata'
$script:Green = [Drawing.Color]::FromArgb(0, 110, 40)
$script:Red = [Drawing.Color]::DarkRed
# Pages are decoded this wide: enough to read small print when zoomed in.
$script:PageWidth = 2400

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

#region Read-only files

function Show-ReadOnly([string] $Heading, [string] $FilePath, [string] $Text) {
    $dialog = New-Object Windows.Forms.Form
    $dialog.Text = "$($script:AppTitle) (read only)"
    $dialog.StartPosition = 'CenterParent'
    $dialog.Size = New-Object Drawing.Size(720, 520)
    $dialog.Font = New-Object Drawing.Font('Segoe UI', 9)
    $dialog.AutoScaleMode = 'Dpi'
    $layout = New-Object Windows.Forms.TableLayoutPanel
    $layout.Dock = 'Fill'
    $layout.Padding = New-Object Windows.Forms.Padding(12)
    foreach ($style in 'AutoSize', 'AutoSize', 'Percent', 'AutoSize') {
        $rowStyle = if ($style -eq 'Percent') { New-Object Windows.Forms.RowStyle('Percent', 100) } else { New-Object Windows.Forms.RowStyle('AutoSize') }
        [void]$layout.RowStyles.Add($rowStyle)
    }
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
    $dialog.CancelButton = $close
    $dialog.Controls.Add($layout)
    [void]$dialog.ShowDialog($script:Form)
    $dialog.Dispose()
}

function Show-LegacyIssue([string] $Folder) {
    $legacy = Join-Path $Folder 'metadata.php'
    Show-ReadOnly ("This issue's metadata is in the older metadata.php file, so it cannot be edited here. " +
        'Edit metadata.php as before; it is shown below.') $legacy ([IO.File]::ReadAllText($legacy))
}

#endregion

#region The page viewer

function Get-PageImage([string] $ImagePath, [int] $Width) {
    # Decoded at $Width: Windows' imaging decoder scales a JPEG while reading it, so a 13 MB scan
    # is not decoded in full.
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
    $encoder = New-Object Windows.Media.Imaging.BmpBitmapEncoder
    $encoder.Frames.Add([Windows.Media.Imaging.BitmapFrame]::Create($decoded))
    $memory = New-Object IO.MemoryStream
    try {
        $encoder.Save($memory)
        $memory.Position = 0
        $image = [Drawing.Image]::FromStream($memory)
        try { New-Object Drawing.Bitmap($image) } finally { $image.Dispose() }
    }
    finally { $memory.Dispose() }
}

# A page viewer: the page fitted to the width, from the top. The wheel zooms at the pointer,
# dragging pans, double-click fits the width again. Builds into $Parent; one viewer at a time.
function New-PageViewer([Windows.Forms.Control] $Parent, [string[]] $Pages) {
    $script:ViewerPages = $Pages
    $script:ViewImage = $null
    $script:View = $null
    $script:Drag = $null
    # Until the user zooms or moves the page, a resize fits it to the width again.
    $script:ViewMoved = $false

    $layout = New-Object Windows.Forms.TableLayoutPanel
    $layout.Dock = 'Fill'
    $layout.ColumnCount = 1
    [void]$layout.RowStyles.Add((New-Object Windows.Forms.RowStyle('AutoSize')))
    [void]$layout.RowStyles.Add((New-Object Windows.Forms.RowStyle('Percent', 100)))
    [void]$layout.RowStyles.Add((New-Object Windows.Forms.RowStyle('AutoSize')))

    $tools = New-Object Windows.Forms.FlowLayoutPanel
    $tools.AutoSize = $true
    $tools.Dock = 'Fill'
    $tools.WrapContents = $false
    $script:PageChoice = New-Object Windows.Forms.ComboBox
    $script:PageChoice.DropDownStyle = 'DropDownList'
    $script:PageChoice.Width = 260
    $script:PageChoice.TabStop = $false
    foreach ($page in $Pages) { [void]$script:PageChoice.Items.Add((Split-Path -Leaf $page)) }
    $tools.Controls.Add($script:PageChoice)
    foreach ($spec in @(@('+', 'Zoom in'), @('-', 'Zoom out'), @('Fit', 'Fit the width (or double-click the page)'), @('Open', 'Open the page in the Windows viewer'))) {
        $button = New-Object Windows.Forms.Button
        $button.Text = $spec[0]
        $button.Tag = $spec[0]
        $button.AutoSize = $true
        $button.MinimumSize = New-Object Drawing.Size(36, 0)
        $button.TabStop = $false
        $script:Tips.SetToolTip($button, $spec[1])
        $button.Add_Click({ param($source) Invoke-ViewerButton $source.Tag })
        $tools.Controls.Add($button)
    }
    $layout.Controls.Add($tools, 0, 0)

    $script:PageBox = New-Object Windows.Forms.PictureBox
    $script:PageBox.Dock = 'Fill'
    $script:PageBox.BackColor = [Drawing.Color]::FromArgb(64, 64, 64)
    $script:PageBox.Cursor = [Windows.Forms.Cursors]::SizeAll
    $script:PageBox.Add_Paint({ param($source, $e) Invoke-ViewerPaint $e.Graphics })
    $script:PageBox.Add_Resize({ Update-ViewerBox })
    $script:PageBox.Add_MouseWheel({
            param($source, $e)
            if (-not $script:View) { return }
            $script:ViewMoved = $true
            [void](Step-NbnpViewZoom $script:View ([Math]::Pow(1.25, $e.Delta / 120.0)) $e.X $e.Y)
            $script:PageBox.Invalidate()
        })
    $script:PageBox.Add_MouseDown({ param($source, $e) $script:Drag = $e.Location })
    $script:PageBox.Add_MouseUp({ $script:Drag = $null; $script:PageBox.Invalidate() })
    $script:PageBox.Add_MouseMove({
            param($source, $e)
            if (-not $script:Drag -or -not $script:View) { return }
            $script:ViewMoved = $true
            [void](Move-NbnpView $script:View ($e.X - $script:Drag.X) ($e.Y - $script:Drag.Y))
            $script:Drag = $e.Location
            $script:PageBox.Invalidate()
        })
    $script:PageBox.Add_DoubleClick({ Invoke-ViewerButton 'Fit' })
    $layout.Controls.Add($script:PageBox, 0, 1)

    $script:PageHint = New-Object Windows.Forms.Label
    $script:PageHint.AutoSize = $true
    $script:PageHint.ForeColor = [Drawing.SystemColors]::GrayText
    $script:PageHint.Text = if ($Pages.Count -gt 0) { 'Wheel: zoom. Drag: move. Double-click: fit.' } else { 'No page images in this folder.' }
    $layout.Controls.Add($script:PageHint, 0, 2)

    $Parent.Controls.Add($layout)
    $script:PageChoice.Add_SelectedIndexChanged({ Show-ViewerPage $script:PageChoice.SelectedIndex })
}

function Show-ViewerPage([int] $Index) {
    if ($Index -lt 0 -or $Index -ge $script:ViewerPages.Count) { return }
    $script:PageHint.Text = "Loading $(Split-Path -Leaf $script:ViewerPages[$Index])..."
    [Windows.Forms.Application]::DoEvents()
    try {
        $image = Get-PageImage $script:ViewerPages[$Index] $script:PageWidth
        if ($script:ViewImage) { $script:ViewImage.Dispose() }
        $script:ViewImage = $image
        $script:ViewMoved = $false
        $script:View = New-NbnpView $image.Width $image.Height ([Math]::Max(1, $script:PageBox.ClientSize.Width)) ([Math]::Max(1, $script:PageBox.ClientSize.Height))
        $script:PageHint.Text = 'Wheel: zoom. Drag: move. Double-click: fit.'
    }
    catch {
        $script:PageHint.Text = "Could not show $(Split-Path -Leaf $script:ViewerPages[$Index]): $($_.Exception.Message)"
    }
    $script:PageBox.Invalidate()
}

function Update-ViewerBox {
    if (-not $script:View) { return }
    $width = [Math]::Max(1, $script:PageBox.ClientSize.Width)
    $height = [Math]::Max(1, $script:PageBox.ClientSize.Height)
    if ($script:ViewMoved) {
        $script:View.BoxWidth = $width
        $script:View.BoxHeight = $height
        [void](Step-NbnpViewZoom $script:View 1 0 0)
    }
    else {
        $script:View = New-NbnpView $script:ViewImage.Width $script:ViewImage.Height $width $height
    }
    $script:PageBox.Invalidate()
}

function Invoke-ViewerButton([string] $Name) {
    if ($Name -eq 'Open') {
        $index = [Math]::Max(0, $script:PageChoice.SelectedIndex)
        if ($script:ViewerPages.Count -gt 0) { Start-Process -FilePath $script:ViewerPages[$index] }
        return
    }
    if (-not $script:View) { return }
    $centreX = $script:View.BoxWidth / 2
    $centreY = $script:View.BoxHeight / 2
    switch ($Name) {
        '+' { $script:ViewMoved = $true; [void](Step-NbnpViewZoom $script:View 1.5 $centreX $centreY) }
        '-' { $script:ViewMoved = $true; [void](Step-NbnpViewZoom $script:View (1 / 1.5) $centreX $centreY) }
        'Fit' {
            $script:ViewMoved = $false
            $script:View = New-NbnpView $script:ViewImage.Width $script:ViewImage.Height $script:View.BoxWidth $script:View.BoxHeight
        }
    }
    $script:PageBox.Invalidate()
}

function Invoke-ViewerPaint([Drawing.Graphics] $Graphics) {
    if (-not $script:ViewImage -or -not $script:View) { return }
    $v = $script:View
    # Smooth when still; quicker while dragging.
    $Graphics.InterpolationMode = if ($script:Drag) { 'Bilinear' } else { 'HighQualityBicubic' }
    $Graphics.PixelOffsetMode = 'Half'
    $destination = New-Object Drawing.RectangleF(0, 0, $v.BoxWidth, $v.BoxHeight)
    $source = New-Object Drawing.RectangleF($v.X, $v.Y, ($v.BoxWidth / $v.Scale), ($v.BoxHeight / $v.Scale))
    $Graphics.DrawImage($script:ViewImage, $destination, $source, [Drawing.GraphicsUnit]::Pixel)
}

function Close-Viewer {
    if ($script:ViewImage) { $script:ViewImage.Dispose() }
    $script:ViewImage = $null
    $script:View = $null
}

#endregion

#region Layout helpers

function New-Grid {
    $grid = New-Object Windows.Forms.TableLayoutPanel
    $grid.ColumnCount = 2
    [void]$grid.ColumnStyles.Add((New-Object Windows.Forms.ColumnStyle('AutoSize')))
    [void]$grid.ColumnStyles.Add((New-Object Windows.Forms.ColumnStyle('Percent', 100)))
    $grid.AutoSize = $true
    $grid.Dock = 'Top'
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
    $label.MaximumSize = New-Object Drawing.Size(420, 0)
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

function Set-TabOrder([Windows.Forms.Control] $Control) {
    $Control.TabIndex = $script:NextTab
    $script:NextTab++
}

# A text field with its label, a short hint under it, and the full guidance as a tooltip.
function Add-TextField([string] $Key, [string] $Label, [string] $Hint, [string] $Tip, [int] $Width = 280, [switch] $Multiline) {
    $box = New-Object Windows.Forms.TextBox
    $box.Width = $Width
    if ($Multiline) {
        $box.Multiline = $true
        $box.AcceptsReturn = $true
        $box.ScrollBars = 'Vertical'
        $box.Height = 64
    }
    $box.Margin = New-Object Windows.Forms.Padding(3, 3, 12, 3)
    $labelControl = New-Label $Label
    Add-Row $labelControl $box
    if ($Hint) { Add-Row $null (New-Label $Hint -Hint) }
    if ($Tip) {
        $script:Tips.SetToolTip($box, $Tip)
        $script:Tips.SetToolTip($labelControl, $Tip)
    }
    Set-TabOrder $box
    $box.Tag = $Key
    $box.Add_Leave({ param($source) [void](Update-Findings -Touched $source.Tag) })
    $script:Inputs[$Key] = $box
    $box
}

function Add-LanguageField([string] $Key, [string] $Label, [string] $Tip, [switch] $AllowEmpty) {
    $combo = New-Object Windows.Forms.ComboBox
    $combo.DropDownStyle = 'DropDownList'
    $combo.Width = 100
    if ($AllowEmpty) { [void]$combo.Items.Add('') }
    foreach ($language in (Get-NbnpLanguages)) { [void]$combo.Items.Add($language) }
    $combo.Margin = New-Object Windows.Forms.Padding(3, 3, 12, 3)
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

function New-Button([string] $Text, [scriptblock] $OnClick, [switch] $Bold) {
    $button = New-Object Windows.Forms.Button
    $button.Text = $Text
    $button.AutoSize = $true
    $button.MinimumSize = New-Object Drawing.Size(90, 28)
    if ($Bold) { $button.Font = New-Object Drawing.Font($script:Form.Font, [Drawing.FontStyle]::Bold) }
    $button.Add_Click($OnClick)
    $button
}

function Set-Status([string] $Text, [switch] $Good) {
    $script:Status.ForeColor = if ($Good) { $script:Green } else { $script:Red }
    $script:Status.Text = $Text
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
    $title = if ($script:ViewKind -eq 'Issue') { $script:TitleFile } else { $null }
    $checked = Test-NbnpMetadata -Kind $script:ViewKind -Values (Get-FormValues) -Title $title -Folder $script:Folder
    $findings = @($checked.Findings)
    if ($script:ViewKind -eq 'Issue') {
        $repeat = Test-NbnpRepeatOfLast -Values $checked.Values -Folder $script:Folder -Last $script:LastIssue
        if ($repeat) { $findings += $repeat }
    }
    [pscustomobject]@{ Values = $checked.Values; Findings = $findings }
}

# Shows findings: all of them, or while typing only those about fields already visited.
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
    Set-Status ($lines -join "`n")
    $findings
}

# Checks and saves the current view's file. Returns $true when saved.
function Save-Current {
    $checked = Test-Form
    $findings = Update-Findings -All
    $errors = @($findings | Where-Object Severity -EQ 'Error')
    if ($errors.Count -gt 0) {
        if ($script:Inputs.Contains($errors[0].Field)) { [void]$script:Inputs[$errors[0].Field].Focus() }
        return $false
    }
    $warnings = @($findings | Where-Object Severity -EQ 'Warning')
    if ($warnings.Count -gt 0) {
        $answer = Show-Message (($warnings.Message -join "`n`n") + "`n`nSave anyway?") 'Question' 'YesNo'
        if ($answer -ne 'Yes') { return $false }
    }
    try {
        $text = ConvertTo-NbnpToml -Kind $script:ViewKind -Values $checked.Values -Header $script:File.Header
        Save-NbnpMetadataFile -Path $script:File.Path -Text $text -ExpectedHash $script:File.Hash
        if ($script:ViewKind -eq 'Issue') { Save-NbnpLastIssue -Values $checked.Values -Folder $script:Folder }
    }
    catch {
        [void](Show-Message "Nothing was saved.`n`n$($_.Exception.Message)" 'Error')
        return $false
    }
    # Later saves in this view compare against what is now on disk.
    $script:File = Read-NbnpMetadataFile -Folder $script:Folder -Kind $script:ViewKind
    $script:Initial = (Get-FormValues | ConvertTo-Json -Compress)
    $true
}

function Test-Unchanged { (Get-FormValues | ConvertTo-Json -Compress) -eq $script:Initial }

# Before leaving a view: $true to go on. Asks when something was typed and not saved.
function Confirm-Leave {
    if (Test-Unchanged) { return $true }
    $answer = Show-Message 'Leave without saving your changes?' 'Question' 'YesNo'
    $answer -eq 'Yes'
}

#endregion

#region Views

# Clears the window for a new view and sets up its parts: the header line, a scrolling column
# of fields, the page viewer, and the bar of messages and buttons, which always stays visible.
function Reset-View([string] $Kind, [string] $Folder) {
    Close-Viewer
    $script:ViewKind = $Kind
    $script:Folder = $Folder
    $script:Inputs = [ordered]@{}
    $script:Touched = @{}
    $script:NextTab = 0
    $script:Supplement = $null
    $script:Errors.Clear()
    # The previous view's controls are taken out now and disposed of once the window has
    # finished the event that got here: a long run of issues would otherwise keep them all, and
    # the button just clicked may still be handling its click.
    foreach ($container in @($script:Body, $script:Buttons, $script:Header)) {
        $script:Disposing += @($container.Controls)
        $container.Controls.Clear()
    }
    [void]$script:Form.BeginInvoke([Action] {
            foreach ($control in $script:Disposing) { $control.Dispose() }
            $script:Disposing = @()
        })
    Set-Status ''

    $split = New-Object Windows.Forms.TableLayoutPanel
    $split.Dock = 'Fill'
    $split.ColumnCount = 2
    [void]$split.ColumnStyles.Add((New-Object Windows.Forms.ColumnStyle('Absolute', 470)))
    [void]$split.ColumnStyles.Add((New-Object Windows.Forms.ColumnStyle('Percent', 100)))
    [void]$split.RowStyles.Add((New-Object Windows.Forms.RowStyle('Percent', 100)))
    $script:Fields = New-Object Windows.Forms.Panel
    $script:Fields.Dock = 'Fill'
    $script:Fields.AutoScroll = $true
    $script:Fields.TabIndex = 0
    $script:ViewerHost = New-Object Windows.Forms.Panel
    $script:ViewerHost.Dock = 'Fill'
    $script:ViewerHost.TabIndex = 1
    $split.Controls.Add($script:Fields, 0, 0)
    $split.Controls.Add($script:ViewerHost, 1, 0)
    $script:Body.Controls.Add($split)

    $script:Grid = New-Grid
    $script:Fields.Controls.Add($script:Grid)
}

function Set-Header([string] $Text, [Windows.Forms.Control] $Extra) {
    $label = New-Object Windows.Forms.Label
    $label.Text = $Text
    $label.AutoSize = $true
    $label.Font = New-Object Drawing.Font($script:Form.Font.FontFamily, 11, [Drawing.FontStyle]::Bold)
    $label.Margin = New-Object Windows.Forms.Padding(3, 6, 12, 3)
    $script:Header.Controls.Add($label)
    if ($Extra) { $script:Header.Controls.Add($Extra) }
}

function Get-TitleName {
    if ($script:TitleFile -and $script:TitleFile.Values.Contains('title')) { $script:TitleFile.Values['title'].Text }
    else { Split-Path -Leaf $script:TitleFolder }
}

function Update-TitleState {
    $script:TitleFile = $null
    $found = Read-NbnpMetadataFile -Folder $script:TitleFolder -Kind Title
    if ($found.Exists -and $found.Supported) { $script:TitleFile = $found }
    $script:Summary = Get-NbnpTitleSummary -TitleFolder $script:TitleFolder
}

function Show-TitleView([string] $Message = '') {
    Update-TitleState
    Reset-View 'Title' $script:TitleFolder
    $script:File = Read-NbnpMetadataFile -Folder $script:TitleFolder -Kind Title
    if ($script:File.Exists -and -not $script:File.Supported) {
        Show-ReadOnly ("This title.toml cannot be edited here, so that nothing in it is lost: $($script:File.Reason). " +
            'Ask whoever maintains the newspaper metadata to look at it.') $script:File.Path $script:File.Text
        $script:Form.Close()
        return
    }
    Set-Header "Title: $(Get-TitleName)"

    Add-Heading 'Title metadata'
    $where = if ($script:File.Exists) { "Editing $($script:File.Path)" } else { "Not created yet. It will be saved as $($script:File.Path)" }
    Add-Row $null (New-Label $where -Hint) -Span
    if (-not $script:File.Exists) {
        $choose = New-Button 'Use another folder for the title...' { Select-TitleFolder }
        $choose.TabStop = $false
        Add-Row $null $choose -Span
    }
    Add-TextField 'title_id' 'Title ID' 'Leave empty if not known yet.' ("The Digital Title ID on the site: in " +
        "https://newspapers.lib.unb.ca/serials/browse/48 it is 48. The title's issues cannot be imported " +
        'until it is filled in.') -Width 100 | Out-Null
    Add-TextField 'title' 'Title' 'Exactly as on the masthead.' 'The exact, complete title from the masthead.' | Out-Null
    Add-LanguageField 'language' 'Language' 'eng for English, fre for French.' | Out-Null
    $media = Add-TextField 'media' 'Media' '' 'The source the scans were made from.' -Width 100
    $media.ReadOnly = $true
    Set-Input 'media' 'print'
    Set-Input 'language' 'eng'
    foreach ($key in @($script:File.Values.Keys)) { if ($script:Inputs.Contains($key)) { Set-Input $key $script:File.Values[$key].Text } }

    Add-Heading 'Issues'
    $s = $script:Summary
    $summaryText = if ($s.Total -eq 0) { 'No issue folders (folders with page images) were found below this folder.' }
    else {
        "$($s.Total) issue folders: $($s.Done) done, $($s.New) need metadata" +
        $(if ($s.Legacy) { ", $($s.Legacy) with an old metadata.php (read only)." } else { '.' })
    }
    Add-Row $null (New-Label $summaryText) -Span
    $script:IssueList = New-Object Windows.Forms.ListBox
    $script:IssueList.Width = 420
    $script:IssueList.Height = 220
    $script:IssueList.Font = New-Object Drawing.Font('Consolas', 9)
    $script:IssueList.TabStop = $false
    $labels = @{ done = 'done   '; new = 'to do  '; legacy = 'old php' }
    foreach ($issue in $s.Issues) { [void]$script:IssueList.Items.Add("$($labels[$issue.State])  $($issue.Name)") }
    Add-Row $null $script:IssueList -Span
    Add-Row $null (New-Label 'Double-click an issue to open it.' -Hint) -Span
    $script:IssueList.Add_DoubleClick({
            $index = $script:IssueList.SelectedIndex
            if ($index -ge 0) { Open-IssueFromTitle $script:Summary.Issues[$index].Path }
        })

    $firstPages = @()
    if ($s.Total -gt 0) {
        $first = Get-NbnpFirstPage -Folder $s.Issues[0].Path
        if ($first) { $firstPages = @($first) }
    }
    New-PageViewer $script:ViewerHost $firstPages

    $enter = New-Button 'Save and enter issues' { Enter-Issues } -Bold
    $script:Tips.SetToolTip($enter, 'Enter. Saves the title, then opens the first issue that needs metadata.')
    $save = New-Button 'Save' { if (Save-Current) { Show-TitleView 'Saved the title.' } }
    $close = New-Button 'Close' { $script:Form.Close() }
    $script:Buttons.Controls.AddRange([Windows.Forms.Control[]]@($close, $save, $enter))
    $script:Form.AcceptButton = $enter
    $script:Form.CancelButton = $close

    $script:Initial = (Get-FormValues | ConvertTo-Json -Compress)
    if ($script:File.Exists) { [void](Update-Findings -All) }
    if ($Message) { Set-Status $Message -Good }
    $script:Focus = if ($script:File.Exists) { $null } else { 'title' }
    Complete-View
}

function Select-TitleFolder {
    $picker = New-Object Windows.Forms.FolderBrowserDialog
    $picker.Description = "Choose the title's folder: the one above its year, volume and issue folders."
    $picker.SelectedPath = $script:TitleFolder
    if ($picker.ShowDialog($script:Form) -eq 'OK') {
        $script:TitleFolder = $picker.SelectedPath
        Show-TitleView
    }
    $picker.Dispose()
}

# Saves the title if needed, then goes to the issue to start at.
function Enter-Issues {
    if (-not $script:File.Exists -or -not (Test-Unchanged)) {
        if (-not (Save-Current)) { return }
        Update-TitleState
    }
    $issues = @($script:Summary.Issues | Where-Object State -NE 'legacy')
    if ($issues.Count -eq 0) {
        Set-Status 'There are no issue folders to enter below this title.'
        return
    }
    $start = $script:StartIssue
    $script:StartIssue = $null
    if (-not $start -or -not ($issues | Where-Object Path -EQ $start)) {
        $start = if ($script:Summary.FirstNew) { $script:Summary.FirstNew } else { $issues[0].Path }
    }
    Show-IssueView $start "Title saved. $($script:Summary.New) issues need metadata."
}

function Open-IssueFromTitle([string] $Folder) {
    $issue = $script:Summary.Issues | Where-Object Path -EQ $Folder
    if ($issue.State -eq 'legacy') { Show-LegacyIssue $Folder; return }
    if (-not $script:File.Exists -or -not (Test-Unchanged)) {
        if (-not (Save-Current)) { return }
        Update-TitleState
    }
    Show-IssueView $Folder
}

function Show-IssueView([string] $Folder, [string] $Arrived = '', [switch] $FromLast) {
    Reset-View 'Issue' $Folder
    $script:File = Read-NbnpMetadataFile -Folder $Folder -Kind Issue
    if (-not $script:File.Supported) {
        Show-ReadOnly ("This issue.toml cannot be edited here, so that nothing in it is lost: $($script:File.Reason). " +
            'Ask whoever maintains the newspaper metadata to look at it.') $script:File.Path $script:File.Text
        Show-TitleView
        return
    }
    $script:LastIssue = Get-NbnpLastIssue
    $script:NextFolder = Find-NbnpNextIssueFolder -IssueFolder $Folder -Issues $script:Summary.Issues
    $script:ParentIssue = Find-NbnpParentIssue -IssueFolder $Folder
    $position = [array]::IndexOf(@($script:Summary.Issues.Path), $Folder) + 1
    $back = New-Button '&Back to title' { if (Confirm-Leave) { Show-TitleView } }
    $back.TabStop = $false
    Set-Header "$(Get-TitleName)  >  $(Split-Path -Leaf $Folder)    (issue $position of $($script:Summary.Total); $($script:Summary.New) need metadata)" $back

    Add-Row $null (New-Label $(if ($script:File.Exists) { 'Editing the existing issue.toml.' } else { 'A new issue.toml will be created.' }) -Hint) -Span
    $fill = New-Button '&Fill from last saved issue' {
        $carried = @($script:Inputs.Keys | Where-Object { $_ -ne 'missing_pages' -and $_ -ne 'errata' })
        if (@($carried | Where-Object { (Get-InputText $_).Trim() -ne '' }).Count -gt 0) {
            $answer = Show-Message 'Replace what is already in the form with the last issue''s values?' 'Question' 'YesNo'
            if ($answer -ne 'Yes') { return }
        }
        Copy-LastIssue
    }
    $fill.TabStop = $false
    $fill.Enabled = $null -ne $script:LastIssue
    Add-Row $null $fill -Span
    $lastHint = if ($script:LastIssue) {
        "Alt+F. Last saved: $(Split-Path -Leaf $script:LastIssue.Folder). Copies all but missing pages and errata."
    }
    else { 'Available once you have saved an issue.' }
    Add-Row $null (New-Label $lastHint -Hint) -Span

    $asPrinted = 'Exactly as printed, even if it is wrong; note a wrong number in Errata. No zero padding: 5, not 05.'
    Add-TextField 'volume' 'Volume' 'As printed; 5, not 05.' $asPrinted -Width 100 | Out-Null
    Add-TextField 'issue' 'Issue (No.)' 'As printed; 5, not 05.' $asPrinted -Width 100 | Out-Null
    $date = Add-TextField 'date' 'Date' 'e.g. 1902-03-28 or March 28, 1902. Day not printed: the 1st.' (
        'The date as printed. Written YYYY-MM-DD; 1902-3-28, March 28, 1902 and 28 March 1902 are also ' +
        'accepted and rewritten. If the day is not printed (a monthly) or you are unsure of it, use the 1st.') -Width 140
    $script:Weekday = New-Label '' -Hint
    Add-Row $null $script:Weekday
    $date.Add_TextChanged({
            $parsed = [datetime]::MinValue
            $normal = ConvertTo-NbnpDate $script:Inputs['date'].Text
            $script:Weekday.Text = if ([datetime]::TryParseExact($normal, 'yyyy-MM-dd',
                    [Globalization.CultureInfo]::InvariantCulture, 'None', [ref]$parsed)) {
                $parsed.ToString('dddd, MMMM d, yyyy', [Globalization.CultureInfo]::GetCultureInfo('en-CA'))
            }
            else { '' }
        })
    Add-TextField 'edition' 'Edition' 'Optional.' 'The edition statement as printed, such as Evening.' | Out-Null
    Add-TextField 'missing_pages' 'Missing pages' 'Optional; e.g. 3, 6-8.' (
        'Page numbers separated by commas, or ranges: 3, 6, 7 or 4-8. Numbers, dashes and commas only; ' +
        'no zero padding.') -Width 140 | Out-Null
    Add-TextField 'errata' 'Errata' 'Optional. Short and clear to everyone.' (
        'For any problem with the printed issue numbering, use "Incorrect issue numbering".') -Multiline | Out-Null
    $phrase = New-Button 'Insert "Incorrect issue numbering"' {
        $text = 'Incorrect issue numbering'
        $box = $script:Inputs['errata']
        if ($box.Text -notmatch [regex]::Escape($text)) {
            $box.Text = if ($box.Text.Trim()) { $box.Text.TrimEnd() + "`r`n" + $text } else { $text }
        }
    }
    $phrase.TabStop = $false
    Add-Row $null $phrase

    $script:Supplement = New-Object Windows.Forms.CheckBox
    $script:Supplement.Text = 'This is a supplement'
    $script:Supplement.AutoSize = $true
    $script:Supplement.Margin = New-Object Windows.Forms.Padding(3, 10, 3, 3)
    Add-Row $null $script:Supplement -Span
    Set-TabOrder $script:Supplement
    $supplementTitle = Add-TextField 'supplement_title' 'Supplement title' 'As printed, if it has one.' (
        'Date, volume and issue are those of the issue the supplement came with.')
    $supplementTitle.Enabled = $false
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

    $script:Differs = New-Object Windows.Forms.CheckBox
    $script:Differs.Text = 'Different from the title...'
    $script:Differs.AutoSize = $true
    $script:Differs.TabStop = $false
    $script:Differs.Margin = New-Object Windows.Forms.Padding(3, 10, 3, 3)
    Add-Row $null $script:Differs -Span
    $script:Tips.SetToolTip($script:Differs, 'Only when this issue differs from its title, such as a masthead printing a different title.')
    $script:Overrides = New-Grid
    $script:Overrides.Visible = $false
    Add-Row $null $script:Overrides -Span
    $fieldsGrid = $script:Grid
    $script:Grid = $script:Overrides
    Add-TextField 'title' 'Title' '' 'The exact, complete title from this issue''s masthead, when it differs from the title''s.' | Out-Null
    Add-LanguageField 'language' 'Language' 'Only when this issue''s language differs from the title''s.' -AllowEmpty | Out-Null
    Add-TextField 'media' 'Media' '' 'The source the scans were made from.' -Width 100 | Out-Null
    Add-TextField 'title_id' 'Title ID' '' 'Only if this issue belongs to a different Digital Title.' -Width 100 | Out-Null
    $script:Grid = $fieldsGrid
    $script:Differs.Add_CheckedChanged({ $script:Overrides.Visible = $script:Differs.Checked })

    $pages = @(Get-ChildItem -LiteralPath $Folder -File | Where-Object { $_.Extension -eq '.jpg' -and -not $_.Name.StartsWith('.') } |
            Sort-Object { Get-NbnpNaturalKey $_.Name } | ForEach-Object FullName)
    New-PageViewer $script:ViewerHost $pages

    $last = -not $script:NextFolder
    $nextText = if ($last) { 'Save and finish' } else { 'Save and next' }
    $saveNext = New-Button $nextText { Invoke-SaveNext } -Bold
    $script:Tips.SetToolTip($saveNext, $(if ($last) { 'Enter or Ctrl+S. The last issue: saves, then returns to the title.' }
            else { "Enter or Ctrl+S. Saves, then opens $(Split-Path -Leaf $script:NextFolder)." }))
    $save = New-Button 'Save' { if (Save-Current) { Set-Status "Saved $(Split-Path -Leaf $script:Folder)." -Good } }
    $close = New-Button 'Close' { $script:Form.Close() }
    $script:Buttons.Controls.AddRange([Windows.Forms.Control[]]@($close, $save, $saveNext))
    $script:Form.AcceptButton = $saveNext
    $script:Form.CancelButton = $close

    foreach ($key in @($script:File.Values.Keys)) { if ($script:Inputs.Contains($key)) { Set-Input $key $script:File.Values[$key].Text } }
    $script:Focus = 'volume'
    if (@('title', 'language', 'media', 'title_id' | Where-Object { $script:File.Values.Contains($_) }).Count -gt 0) {
        $script:Differs.Checked = $true
    }
    if ($script:File.Values.Contains('supplement_title')) { $script:Supplement.Checked = $true }
    if (-not $script:File.Exists) {
        $parentFile = if ($script:ParentIssue) { Join-Path $script:ParentIssue 'issue.toml' } else { $null }
        if ($parentFile -and (Test-Path -LiteralPath $parentFile)) {
            # A supplement folder beside its issue: its date, volume and number are the issue's.
            $script:Supplement.Checked = $true
            $script:Focus = 'supplement_title'
        }
        elseif ($FromLast -and $script:LastIssue) {
            Copy-LastIssue
            $script:Focus = 'issue'
        }
    }
    $script:Initial = (Get-FormValues | ConvertTo-Json -Compress)
    if ($script:File.Exists) { [void](Update-Findings -All) }
    if ($Arrived) { Set-Status $Arrived -Good }
    Complete-View
}

# Copies the last issue saved into the form, all but missing pages and errata. A supplement's
# title carries over only into another supplement's folder.
function Copy-LastIssue {
    $last = $script:LastIssue.Values
    $carrySupplement = $null -ne $script:ParentIssue
    foreach ($key in @($script:Inputs.Keys | Where-Object { $_ -ne 'missing_pages' -and $_ -ne 'errata' })) {
        if ($key -eq 'supplement_title' -and -not $carrySupplement) { Set-Input $key ''; continue }
        $value = if ($last.Contains($key)) { $last[$key].Text } else { '' }
        Set-Input $key $value
    }
    $script:Supplement.Checked = $carrySupplement -and $last.Contains('supplement_title')
    $script:Focus = 'issue'
    $box = $script:Inputs['issue']
    if ($box.Visible) { [void]$box.Focus(); $box.SelectAll() }
}

function Invoke-SaveNext {
    if (-not (Save-Current)) { return }
    $saved = Split-Path -Leaf $script:Folder
    $script:Summary = Get-NbnpTitleSummary -TitleFolder $script:TitleFolder
    if ($script:NextFolder) {
        Show-IssueView $script:NextFolder "Saved $saved. Now $(Split-Path -Leaf $script:NextFolder)." -FromLast
    }
    else {
        $left = $script:Summary.New
        Show-TitleView ("Saved $saved, the last issue. " + $(if ($left) { "$left issues still need metadata." } else { 'Every issue has metadata.' }))
    }
}

# After a view is built: show its first page and put the cursor where typing starts.
function Complete-View {
    $script:Body.PerformLayout()
    if ($script:PageChoice.Items.Count -gt 0) { $script:PageChoice.SelectedIndex = 0 }
    if ($script:Focus -and $script:Inputs.Contains($script:Focus)) {
        $box = $script:Inputs[$script:Focus]
        [void]$box.Focus()
        if ($box -is [Windows.Forms.TextBox]) { $box.SelectAll() }
    }
}

#endregion

#region The window

$script:Form = New-Object Windows.Forms.Form
$script:Form.Text = $script:AppTitle
$script:Form.Font = New-Object Drawing.Font('Segoe UI', 9)
$script:Form.AutoScaleMode = 'Dpi'
$script:Form.KeyPreview = $true
$script:Form.StartPosition = 'CenterScreen'
$script:Form.Size = New-Object Drawing.Size(1200, 780)
$script:Form.MinimumSize = New-Object Drawing.Size(900, 560)

$script:Errors = New-Object Windows.Forms.ErrorProvider
$script:Errors.BlinkStyle = 'NeverBlink'
$script:Tips = New-Object Windows.Forms.ToolTip
$script:Tips.AutoPopDelay = 20000
$script:Disposing = @()
$script:ViewImage = $null
$script:PageChoice = $null

# Docked parts: the header across the top, the message and button bar across the bottom (always
# on screen, whatever the window's size), and the body between them.
$script:Header = New-Object Windows.Forms.FlowLayoutPanel
$script:Header.Dock = 'Top'
$script:Header.AutoSize = $true
$script:Header.Padding = New-Object Windows.Forms.Padding(8, 6, 8, 0)
$script:Header.WrapContents = $false

$bottom = New-Object Windows.Forms.TableLayoutPanel
$bottom.Dock = 'Bottom'
$bottom.AutoSize = $true
$bottom.ColumnCount = 2
[void]$bottom.ColumnStyles.Add((New-Object Windows.Forms.ColumnStyle('Percent', 100)))
[void]$bottom.ColumnStyles.Add((New-Object Windows.Forms.ColumnStyle('AutoSize')))
$bottom.Padding = New-Object Windows.Forms.Padding(8, 4, 8, 8)
$script:Status = New-Object Windows.Forms.Label
$script:Status.AutoSize = $true
$script:Status.MaximumSize = New-Object Drawing.Size(700, 0)
$script:Status.Margin = New-Object Windows.Forms.Padding(3, 6, 12, 3)
$script:Buttons = New-Object Windows.Forms.FlowLayoutPanel
$script:Buttons.FlowDirection = 'RightToLeft'
$script:Buttons.AutoSize = $true
$script:Buttons.WrapContents = $false
$bottom.Controls.Add($script:Status, 0, 0)
$bottom.Controls.Add($script:Buttons, 1, 0)

$script:Body = New-Object Windows.Forms.Panel
$script:Body.Dock = 'Fill'
$script:Body.Padding = New-Object Windows.Forms.Padding(8, 4, 8, 0)

# Docking order: the fill goes in first, so it takes what the top and bottom bars leave.
$script:Form.Controls.Add($script:Body)
$script:Form.Controls.Add($bottom)
$script:Form.Controls.Add($script:Header)

$script:Form.Add_KeyDown({
        param($source, $e)
        if ($e.Control -and $e.KeyCode -eq 'S') {
            $e.SuppressKeyPress = $true
            $script:Form.AcceptButton.PerformClick()
        }
    })
$script:Form.Add_FormClosing({
        param($source, $e)
        if (-not (Confirm-Leave)) { $e.Cancel = $true }
    })

# Where to start: the title's folder, and the issue the menu was opened on, if it was one.
$title = Find-NbnpTitleFolder -Folder $Path
$script:TitleFolder = $title.Path
$script:StartIssue = $null
$script:ViewKind = 'Title'
$script:Inputs = [ordered]@{}
$script:Initial = (Get-FormValues | ConvertTo-Json -Compress)
$openedOnIssue = $null -ne (Get-NbnpFirstPage -Folder $Path)
$script:Form.Add_Shown({
        if ($openedOnIssue -and $title.Exists) {
            Update-TitleState
            if (Test-Path -LiteralPath (Join-Path $Path 'metadata.php') -PathType Leaf) {
                Show-LegacyIssue $Path
                Show-TitleView
            }
            else { Show-IssueView $Path }
        }
        else {
            if ($openedOnIssue) { $script:StartIssue = $Path }
            Show-TitleView $(if (-not $title.Exists) { 'This title has no title.toml yet: fill in the title first, then Save and enter issues.' } else { '' })
        }
    })

[void]$script:Form.ShowDialog()
Close-Viewer
$script:Form.Dispose()

#endregion
