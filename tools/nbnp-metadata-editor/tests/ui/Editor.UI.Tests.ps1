#Requires -Version 5.1
<#
    Drives the real editor window on Windows with UI Automation: starts Edit-NbnpMetadata.ps1 on a
    small fixture tree, types into its fields, clicks its buttons and answers its dialogs, then
    checks the files it wrote. A screenshot is taken at each step, into $env:NBNP_UI_SHOTS.

    Windows only, in Windows PowerShell 5.1 with Pester 5: run Invoke-UiTests.ps1, which also
    fetches FlaUI (UI Automation through Windows' own UIA3 COM interface; the .NET Framework's
    managed client sees every control as a bare pane on current Windows Server images). The tests run
    in order and share one fixture tree, as one user's session would.
#>

BeforeAll {
    Add-Type -AssemblyName System.Windows.Forms, System.Drawing
    foreach ($dll in 'Interop.UIAutomationClient.dll', 'FlaUI.Core.dll', 'FlaUI.UIA3.dll') {
        Add-Type -Path (Join-Path $env:NBNP_FLAUI $dll)
    }
    $script:Auto = New-Object FlaUI.UIA3.UIA3Automation
    $script:Cf = $script:Auto.ConditionFactory
    $script:Type = [FlaUI.Core.Definitions.ControlType]

    $script:Editor = (Resolve-Path (Join-Path $PSScriptRoot '..\..\Edit-NbnpMetadata.ps1')).Path
    $script:Root = Join-Path ([IO.Path]::GetTempPath()) "nbnp-ui-$PID"
    $script:State = Join-Path $script:Root 'state'
    $script:Shots = if ($env:NBNP_UI_SHOTS) { $env:NBNP_UI_SHOTS } else { Join-Path $script:Root 'shots' }
    $script:Shot = 0
    $script:Pending = New-Object System.Collections.Generic.List[object]
    $script:Process = $null
    [void](New-Item -ItemType Directory -Force -Path $script:Shots)


    # A page image with its masthead text, so the viewer has a real JPEG to show.
    function New-Page([string] $Folder, [string] $Leaf, [string] $Text) {
        [void](New-Item -ItemType Directory -Force -Path $Folder)
        $bitmap = New-Object System.Drawing.Bitmap(600, 900)
        $graphics = [System.Drawing.Graphics]::FromImage($bitmap)
        $graphics.Clear([System.Drawing.Color]::White)
        $font = New-Object System.Drawing.Font('Times New Roman', 28)
        $graphics.DrawString($Text, $font, [System.Drawing.Brushes]::Black, 20, 20)
        $bitmap.Save((Join-Path $Folder "001_$Leaf`_01.jpg"), [System.Drawing.Imaging.ImageFormat]::Jpeg)
        $font.Dispose(); $graphics.Dispose(); $bitmap.Dispose()
    }

    # The probe's parameter name must not be one the probes use: PowerShell names ignore case,
    # so a probe's $condition would otherwise be this function's own parameter.
    function Wait-Until([scriptblock] $Probe, [string] $What, [int] $Seconds = 20) {
        $deadline = (Get-Date).AddSeconds($Seconds)
        while ((Get-Date) -lt $deadline) {
            $result = & $Probe
            if ($result) { return $result }
            Start-Sleep -Milliseconds 200
        }
        Save-Shot "timeout"
        throw "Timed out after $Seconds s waiting for: $What"
    }

    function Save-Shot([string] $Name) {
        $script:Shot++
        $bounds = [System.Windows.Forms.Screen]::PrimaryScreen.Bounds
        $bitmap = New-Object System.Drawing.Bitmap($bounds.Width, $bounds.Height)
        $graphics = [System.Drawing.Graphics]::FromImage($bitmap)
        try {
            $graphics.CopyFromScreen($bounds.Location, [System.Drawing.Point]::Empty, $bounds.Size)
            $bitmap.Save((Join-Path $script:Shots ('{0:D2}-{1}.png' -f $script:Shot, $Name)), [System.Drawing.Imaging.ImageFormat]::Png)
        }
        catch { Write-Warning "No screenshot: $($_.Exception.Message)" }
        finally { $graphics.Dispose(); $bitmap.Dispose() }
    }

    # Starts the editor as Explorer's menu would, with this test's own per-user state folder.
    function Start-Editor([string] $Folder) {
        $saved = $env:LOCALAPPDATA
        $env:LOCALAPPDATA = $script:State
        try {
            $arguments = "-NoProfile -ExecutionPolicy Bypass -STA -WindowStyle Hidden -File `"$script:Editor`" -Path `"$Folder`""
            $script:Process = Start-Process powershell.exe -ArgumentList $arguments -PassThru
        }
        finally { $env:LOCALAPPDATA = $saved }
    }

    function Get-Windows {
        if (-not $script:Process -or $script:Process.HasExited) { return @() }
        $top = @($script:Auto.GetDesktop().FindAllChildren($script:Cf.ByProcessId($script:Process.Id)))
        # A modal dialog can also appear under the window that owns it.
        $owned = @(foreach ($window in $top) { $window.FindAllChildren($script:Cf.ByControlType($script:Type::Window)) })
        @($top) + @($owned)
    }

    function Get-MainWindow {
        Wait-Until { Get-Windows | Where-Object { $_.ClassName -ne '#32770' -and $_.Name -match '^NBNP Metadata [0-9.]+$' } |
                Select-Object -First 1 } 'the editor window'
    }

    # A message box, once its text is there.
    function Find-MessageBox([int] $Seconds = 20) {
        Wait-Until {
            Get-Windows | Where-Object { $_.ClassName -eq '#32770' -and (Get-Text $_) } | Select-Object -First 1
        } 'a message box' $Seconds
    }

    function Test-NoMessageBox([int] $Seconds = 3) {
        Start-Sleep -Seconds $Seconds
        -not (Get-Windows | Where-Object { $_.ClassName -eq '#32770' })
    }

    # Every name in a window: a message box's text is a Text element, or a Document on some
    # versions of Windows.
    function Get-Text($Element) {
        (@($Element.FindAllDescendants()) | Where-Object { $_.ControlType -ne $script:Type::Button } |
            ForEach-Object { $_.Name } | Where-Object { $_ }) -join "`n"
    }

    # What UI Automation sees in a window, for the log when a control is not found.
    function Write-Tree($Within) {
        try {
            $all = @($Within.FindAllDescendants())
            Write-Host "UI tree of '$($Within.Name)' ($($all.Count) elements):"
            foreach ($c in $all) {
                Write-Host ("  {0} name='{1}' id='{2}' class='{3}' enabled={4} offscreen={5} rect={6}" -f
                    $c.ControlType, $c.Name, $c.AutomationId, $c.ClassName, $c.IsEnabled, $c.IsOffscreen, $c.BoundingRectangle)
            }
        }
        catch { Write-Host "UI tree not readable: $($_.Exception.Message)" }
    }

    # Searches by walking the elements here, not with a UI Automation condition.
    function Find-Element($Within, [scriptblock] $Match, [string] $What) {
        $deadline = (Get-Date).AddSeconds(20)
        while ((Get-Date) -lt $deadline) {
            foreach ($e in @($Within.FindAllDescendants())) {
                try { if (& $Match $e) { return $e } } catch { Write-Verbose $_.Exception.Message }
            }
            Start-Sleep -Milliseconds 200
        }
        Save-Shot 'timeout'
        Write-Tree $Within
        throw "Timed out after 20 s waiting for: $What"
    }

    function Find-ById($Within, [string] $Id) {
        Find-Element $Within { param($c) $c.AutomationId -eq $Id } "control $Id"
    }

    function Find-Button($Within, [string] $Name) {
        Find-Element $Within {
            param($c) $c.ControlType -eq $script:Type::Button -and $c.Name -eq $Name
        } "button $Name"
    }

    # A real mouse click, which returns at once even when it opens a modal dialog. (Invoking the
    # button through UI Automation waits for the dialog to close, on the test's own thread.)
    function Invoke-Button($Element) {
        $Element.Focus()
        $Element.Click()
        Start-Sleep -Milliseconds 300
    }

    function Set-Field($Window, [string] $Key, [string] $Text) {
        $field = Find-ById $Window "field_$Key"
        $field.Focus()
        $field.Patterns.Value.Pattern.SetValue($Text)
    }

    function Get-Field($Window, [string] $Key) {
        (Find-ById $Window "field_$Key").Patterns.Value.Pattern.Value.Value
    }

    function Get-Label($Window, [string] $Id) { (Find-ById $Window $Id).Name }

    # The bottom bar's buttons must be inside the window and on the screen: an earlier version
    # put them below the bottom of a laptop's screen.
    function Assert-OnScreen($Window, [string[]] $Names) {
        $area = [System.Windows.Forms.Screen]::PrimaryScreen.WorkingArea
        $frame = $Window.BoundingRectangle
        foreach ($name in $Names) {
            $box = (Find-Button $Window $name).BoundingRectangle
            $where = "button $name at $box; window $frame; screen $area"
            $box.IsEmpty | Should -BeFalse -Because $where
            $box.Left | Should -BeGreaterOrEqual $frame.Left -Because $where
            $box.Right | Should -BeLessOrEqual $frame.Right -Because $where
            $box.Bottom | Should -BeLessOrEqual $frame.Bottom -Because $where
            $box.Bottom | Should -BeLessOrEqual ($area.Bottom + 1) -Because $where
            $box.Right | Should -BeLessOrEqual ($area.Right + 1) -Because $where
        }
    }

    function Wait-Exit([int] $Seconds = 15) {
        Wait-Until { $script:Process.HasExited } 'the editor to close' $Seconds | Out-Null
    }

    function Stop-Editor {
        if ($script:Process -and -not $script:Process.HasExited) { $script:Process.Kill() }
        $script:Process = $null
    }

    function Read-Text([string] $Path) { [IO.File]::ReadAllText($Path) }

    # The fixture: two titles. Kings County Record has no title.toml yet, four issues to enter
    # and one already imported; The News has its title.toml and one issue.
    $script:Nbnp = Join-Path $script:Root 'NBNP'
    $kcr = Join-Path $script:Nbnp 'KingsCountyRecord\KCR_1902'
    $script:Kcr = Join-Path $script:Nbnp 'KingsCountyRecord'
    $script:Issue = @{}
    foreach ($leaf in 'KCR_1902_16_24', 'KCR_1902_16_25', 'KCR_1902_17_26', 'KCR_1902_17_27', 'KCR_1902_18_30') {
        $parts = $leaf -split '_'
        $folder = Join-Path $kcr ("KCR_1902_{0}\{1}" -f $parts[2], $leaf)
        New-Page $folder $leaf "THE KINGS COUNTY RECORD  VOL. $($parts[2])  No. $($parts[3])"
        $script:Issue[$leaf] = $folder
    }
    [IO.File]::WriteAllText((Join-Path $script:Issue['KCR_1902_17_27'] 'metadata.php'), "<?php define('ISSUE_ISSUE', '27');")
    $tn = Join-Path $script:Nbnp 'The_News\TN_1909\TN_1909_33\TN_1909_33_05'
    New-Page $tn 'TN_1909_33_05' 'THE NEWS  VOL. 33  No. 5'
    $script:Issue['TN_1909_33_05'] = $tn
    [IO.File]::WriteAllText((Join-Path $script:Nbnp 'The_News\title.toml'),
        "title_id = 248`ntitle = `"The News`"`nlanguage = `"eng`"`nmedia = `"print`"`n")

    $screen = [System.Windows.Forms.Screen]::PrimaryScreen
    Write-Host "Screen $($screen.Bounds), working area $($screen.WorkingArea); fixture $script:Root"
}

AfterAll {
    Stop-Editor
    foreach ($pending in $script:Pending) {
        try { $pending[0].Stop(); $pending[0].Dispose() } catch { Write-Verbose $_.Exception.Message }
    }
    Remove-Item -LiteralPath (Join-Path $script:Root 'NBNP') -Recurse -Force -ErrorAction SilentlyContinue
}

Describe 'The editor window' {
    AfterEach { Stop-Editor }

    It 'refuses a folder above a title, with a message and no window' {
        Start-Editor $script:Nbnp
        $box = Find-MessageBox
        Save-Shot 'above-a-title'
        Get-Text $box | Should -Match 'above a title'
        Invoke-Button (Find-Button $box 'OK')
        Wait-Exit
    }

    It 'opens on the title when the title has no title.toml, with its issues summed up' {
        Start-Editor $script:Issue['KCR_1902_16_25']
        $window = Get-MainWindow
        Save-Shot 'title-view'
        Get-Label $window 'status' | Should -Match 'no title.toml'
        Get-Label $window 'summary' | Should -Be '5 issue folders: 0 done, 4 need metadata, 1 already imported.'
        Assert-OnScreen $window @('Save and enter issues', 'Save', 'Close')
    }

    It 'saves the title, then enters issues at the one it was opened on, and saves it and moves on' {
        Start-Editor $script:Issue['KCR_1902_16_25']
        $window = Get-MainWindow
        Set-Field $window 'title' 'The Kings County Record'
        Invoke-Button (Find-Button $window 'Save and enter issues')
        # The Title ID is not known yet: a warning, and saving anyway is allowed.
        $box = Find-MessageBox
        Get-Text $box | Should -Match 'Title ID is not set'
        Invoke-Button (Find-Button $box 'Yes')
        Wait-Until { (Get-Label $window 'header') -match 'KCR_1902_16_25' } 'the issue view' | Out-Null
        Read-Text (Join-Path $script:Kcr 'title.toml') | Should -Match 'title = "The Kings County Record"'
        Assert-OnScreen $window @('Save and next', 'Save', 'Close', 'Warnings...')

        # The smallest window the editor allows still shows the buttons.
        $window.Patterns.Transform.Pattern.Resize(900, 560)
        Start-Sleep -Milliseconds 500
        Save-Shot 'issue-view-small'
        Assert-OnScreen $window @('Save and next', 'Save', 'Close', 'Warnings...')

        Set-Field $window 'volume' '16'
        Set-Field $window 'issue' '25'
        Set-Field $window 'date' 'March 14, 1902'
        Save-Shot 'issue-filled'
        Invoke-Button (Find-Button $window 'Save and next')
        Wait-Until { (Get-Label $window 'header') -match 'KCR_1902_17_26' } 'the next issue' | Out-Null
        $saved = Read-Text (Join-Path $script:Issue['KCR_1902_16_25'] 'issue.toml')
        $saved | Should -Match 'date = 1902-03-14'
        $saved | Should -Match 'volume = "16"'
        $saved | Should -Match 'issue = "25"'
        # The next issue arrives filled from the one just saved.
        Get-Field $window 'volume' | Should -Be '16'
        Save-Shot 'next-issue-filled'
    }

    It 'warns before saving an issue that repeats another, and No saves nothing' {
        Start-Editor $script:Issue['KCR_1902_17_26']
        $window = Get-MainWindow
        Invoke-Button (Find-Button $window 'Fill from last saved issue')
        Wait-Until { (Get-Field $window 'issue') -eq '25' } 'the filled form' | Out-Null
        Invoke-Button (Find-Button $window 'Save and next')
        $box = Find-MessageBox
        Save-Shot 'save-warnings'
        $text = Get-Text $box
        $text | Should -Match 'Same date as KCR_1902_16_25\.'
        $text | Should -Match 'Same volume and issue as KCR_1902_16_25\.'
        $text | Should -Match 'differs from the folder name'
        Invoke-Button (Find-Button $box 'No')
        Wait-Until { Test-NoMessageBox 1 } 'the warning to close' | Out-Null
        Test-Path (Join-Path $script:Issue['KCR_1902_17_26'] 'issue.toml') | Should -BeFalse

        Set-Field $window 'volume' '17'
        Set-Field $window 'issue' '26'
        Set-Field $window 'date' '1902-03-21'
        Invoke-Button (Find-Button $window 'Save and next')
        # KCR_1902_17_27 is already imported, so the next is KCR_1902_18_30.
        Wait-Until { (Get-Label $window 'header') -match 'KCR_1902_18_30' } 'the issue after the imported one' | Out-Null
        Read-Text (Join-Path $script:Issue['KCR_1902_17_26'] 'issue.toml') | Should -Match 'issue = "26"'
    }

    It 'asks before closing with unsaved changes' {
        Start-Editor $script:Issue['KCR_1902_18_30']
        $window = Get-MainWindow
        Set-Field $window 'volume' '18'
        Invoke-Button (Find-Button $window 'Close')
        $box = Find-MessageBox
        Get-Text $box | Should -Match 'Leave without saving'
        Invoke-Button (Find-Button $box 'Yes')
        Wait-Exit
        Test-Path (Join-Path $script:Issue['KCR_1902_18_30'] 'issue.toml') | Should -BeFalse
    }

    It 'turns a warning off for good from Warnings...' {
        Start-Editor $script:Issue['KCR_1902_16_24']
        $window = Get-MainWindow
        Invoke-Button (Find-Button $window 'Warnings...')
        $dialog = Wait-Until { Get-Windows | Where-Object { $_.Name -like '*: warnings' } | Select-Object -First 1 } 'the warnings window'
        $item = Find-Element $dialog { param($c) $c.Name -eq 'Same date as another issue of the title' } 'the same-date item'
        if ($item.Patterns.Toggle.IsSupported) { $item.Patterns.Toggle.Pattern.Toggle() }
        else {
            $box = $item.BoundingRectangle
            [FlaUI.Core.Input.Mouse]::Click((New-Object System.Drawing.Point(($box.Left + 8), [int]($box.Top + $box.Height / 2))))
        }
        Save-Shot 'warnings-settings'
        Invoke-Button (Find-Button $dialog 'OK')
        Wait-Until { Test-Path (Join-Path $script:State 'nbnp-metadata-editor\settings.json') } 'settings.json' | Out-Null
        Read-Text (Join-Path $script:State 'nbnp-metadata-editor\settings.json') | Should -Match '"same-date"'

        # The same date as KCR_1902_16_25 now saves without asking.
        Set-Field $window 'volume' '16'
        Set-Field $window 'issue' '24'
        Set-Field $window 'date' '1902-03-14'
        Invoke-Button (Find-Button $window 'Save')
        Wait-Until { Test-Path (Join-Path $script:Issue['KCR_1902_16_24'] 'issue.toml') } 'the save' | Out-Null
        Test-NoMessageBox 1 | Should -BeTrue
        Get-Label $window 'status' | Should -Be 'Saved KCR_1902_16_24.'
    }

    It 'offers Fill from last saved issue only within the same title' {
        Start-Editor $script:Issue['TN_1909_33_05']
        $window = Get-MainWindow
        Set-Field $window 'volume' '33'
        Set-Field $window 'issue' '5'
        Set-Field $window 'date' '1909-02-05'
        Invoke-Button (Find-Button $window 'Save and finish')
        Wait-Until { (Get-Label $window 'summary') -match '1 done' } 'the title view' | Out-Null
        Stop-Editor

        Start-Editor $script:Issue['KCR_1902_18_30']
        $window = Get-MainWindow
        Save-Shot 'fill-other-title'
        (Find-Button $window 'Fill from last saved issue').IsEnabled | Should -BeFalse
    }

    It 'shows an issue already imported as such, and does not open it' {
        Start-Editor $script:Issue['KCR_1902_17_27']
        $box = Find-MessageBox
        Get-Text $box | Should -Match 'KCR_1902_17_27 has already been imported'
        Invoke-Button (Find-Button $box 'OK')
        $window = Get-MainWindow
        Get-Label $window 'summary' | Should -Be '5 issue folders: 3 done, 1 need metadata, 1 already imported.'
        Save-Shot 'title-view-after'
    }
}
