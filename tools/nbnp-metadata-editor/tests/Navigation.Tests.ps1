# Moving through a run of issues: next folder, first page, dates typed loosely, and repeats.

BeforeAll {
    Import-Module (Join-Path $PSScriptRoot '..' 'NbnpMetadata.psm1') -Force

    function New-Issue([string] $Path, [switch] $Legacy, [switch] $NoPages) {
        New-Item -ItemType Directory -Force -Path $Path | Out-Null
        if (-not $NoPages) {
            $leaf = Split-Path -Leaf $Path
            foreach ($n in 2, 1, 10) { [IO.File]::WriteAllText((Join-Path $Path ('{0:D3}_{1}_{0:D2}.jpg' -f $n, $leaf)), 'x') }
            [IO.File]::WriteAllText((Join-Path $Path "._001_$leaf.jpg"), 'x')
        }
        if ($Legacy) { [IO.File]::WriteAllText((Join-Path $Path 'metadata.php'), "define('ISSUE_ISSUE', '1');") }
    }

    $script:Year = Join-Path $TestDrive 'KCR_1906'
    $v19 = Join-Path $Year 'KCR_1906_19'
    $v20 = Join-Path $Year 'KCR_1906_20'
    New-Issue (Join-Path $v19 'KCR_1906_19_9')
    New-Issue (Join-Path $v19 'KCR_1906_19_10')
    New-Issue (Join-Path $v19 'KCR_1906_19_10_Sup')
    New-Issue (Join-Path $v19 'KCR_1906_19_11') -Legacy
    New-Issue (Join-Path $v19 'KCR_1906_19_12') -NoPages
    New-Issue (Join-Path $v19 'KCR_1906_19_13')
    New-Issue (Join-Path $v20 'KCR_1906_20_1') -Legacy
    New-Issue (Join-Path $v20 'KCR_1906_20_2')
}

Describe 'Next issue folder' {
    It 'goes from <From> to <To>' -ForEach @(
        @{ From = 'KCR_1906_19\KCR_1906_19_9'; To = 'KCR_1906_19\KCR_1906_19_10' }
        @{ From = 'KCR_1906_19\KCR_1906_19_10'; To = 'KCR_1906_19\KCR_1906_19_10_Sup' }
        @{ From = 'KCR_1906_19\KCR_1906_19_10_Sup'; To = 'KCR_1906_19\KCR_1906_19_13' }
        @{ From = 'KCR_1906_19\KCR_1906_19_13'; To = 'KCR_1906_20\KCR_1906_20_2' }
    ) {
        $from = Join-Path $script:Year ($From -replace '\\', [IO.Path]::DirectorySeparatorChar)
        $to = Join-Path $script:Year ($To -replace '\\', [IO.Path]::DirectorySeparatorChar)
        Find-NbnpNextIssueFolder -IssueFolder $from | Should -Be $to
    }

    It 'has no next after the last issue' {
        Find-NbnpNextIssueFolder -IssueFolder (Join-Path $script:Year 'KCR_1906_20' 'KCR_1906_20_2') | Should -BeNullOrEmpty
    }

    It 'sorts names with numbers as people read them' {
        @('TN_10', 'TN_9', 'TN_1', 'TN_10_Sup') | Sort-Object { Get-NbnpNaturalKey $_ } |
            Should -Be @('TN_1', 'TN_9', 'TN_10', 'TN_10_Sup')
    }
}

Describe 'First page' {
    It 'is the first page image in name order, ignoring ._ copies' {
        $folder = Join-Path $script:Year 'KCR_1906_19' 'KCR_1906_19_9'
        Split-Path -Leaf (Get-NbnpFirstPage -Folder $folder) | Should -Be '001_KCR_1906_19_9_01.jpg'
    }

    It 'is $null in a folder without page images' {
        Get-NbnpFirstPage -Folder (Join-Path $script:Year 'KCR_1906_19' 'KCR_1906_19_12') | Should -BeNullOrEmpty
    }
}

Describe 'Dates typed loosely' {
    It 'reads <Typed> as <Date>' -ForEach @(
        @{ Typed = '1902-03-28'; Date = '1902-03-28' }, @{ Typed = '1902-3-28'; Date = '1902-03-28' }
        @{ Typed = ' March 28, 1902 '; Date = '1902-03-28' }, @{ Typed = 'Mar. 28 1902'; Date = '1902-03-28' }
        @{ Typed = '28 March 1902'; Date = '1902-03-28' }, @{ Typed = '28 mar 1902'; Date = '1902-03-28' }
    ) {
        ConvertTo-NbnpDate $Typed | Should -Be $Date
    }

    It 'leaves <Typed> for the check to refuse' -ForEach @(
        @{ Typed = '03/04/1902' }, @{ Typed = '28/03/1902' }, @{ Typed = '1902-02-30' }, @{ Typed = 'March 1902' }
    ) {
        ConvertTo-NbnpDate $Typed | Should -Be $Typed
    }

    It 'saves a loosely typed date as YYYY-MM-DD' {
        $values = [ordered]@{ date = New-NbnpValue 'March 28, 1902' 'bare'; volume = New-NbnpValue '18'; issue = New-NbnpValue '27'
            title = New-NbnpValue 'The Kings County Record'; language = New-NbnpValue 'eng'; media = New-NbnpValue 'print' }
        $checked = Test-NbnpMetadata -Kind Issue -Values $values -Today ([datetime]'2026-11-18')
        $checked.Findings | Should -BeNullOrEmpty
        $checked.Values['date'].Text | Should -Be '1902-03-28'
    }
}

Describe 'Repeating the last issue' {
    BeforeAll {
        $script:Last = [pscustomobject]@{
            Folder = 'X:\KCR_1906_19_9'
            Values = [ordered]@{ date = New-NbnpValue '1906-03-02' 'bare'; volume = New-NbnpValue '19'; issue = New-NbnpValue '9' }
        }
        function Values([string] $Issue, [string] $Date = '1906-03-02') {
            [ordered]@{ date = New-NbnpValue $Date 'bare'; volume = New-NbnpValue '19'; issue = New-NbnpValue $Issue }
        }
    }

    It 'warns when another folder gets the same volume, issue and date' {
        $finding = Test-NbnpRepeatOfLast -Values (Values '9') -Folder 'X:\KCR_1906_19_10' -Last $script:Last
        $finding.Severity | Should -Be 'Warning'
        $finding.Message | Should -Match 'KCR_1906_19_9'
    }

    It 'is quiet when <Case>' -ForEach @(
        @{ Case = 'the number changed'; Issue = '10'; Date = '1906-03-02'; Folder = 'X:\KCR_1906_19_10' }
        @{ Case = 'the date changed'; Issue = '9'; Date = '1906-03-09'; Folder = 'X:\KCR_1906_19_10' }
        @{ Case = 'it is the same folder saved again'; Issue = '9'; Date = '1906-03-02'; Folder = 'X:\KCR_1906_19_9' }
    ) {
        Test-NbnpRepeatOfLast -Values (Values $Issue $Date) -Folder $Folder -Last $script:Last | Should -BeNullOrEmpty
    }

    It 'is quiet with no last issue' {
        Test-NbnpRepeatOfLast -Values (Values '9') -Folder 'X:\KCR_1906_19_10' -Last $null | Should -BeNullOrEmpty
    }
}

Describe 'A title''s issues' {
    BeforeAll {
        $script:TitleRoot = Join-Path $TestDrive 'KingsCountyRecord'
        $y = Join-Path $TitleRoot 'KCR_1902'
        New-Issue (Join-Path $y 'KCR_1902_17' 'KCR_1902_17_26')
        New-Issue (Join-Path $y 'KCR_1902_16' 'KCR_1902_16_25')
        New-Issue (Join-Path $y 'KCR_1902_18' 'KCR_1902_18_27') -Legacy
        New-Issue (Join-Path $y 'KCR_1902_16' 'KCR_1902_16_24')
        [IO.File]::WriteAllText((Join-Path $y 'KCR_1902_16' 'KCR_1902_16_24' 'issue.toml'), "volume = `"16`"`n")
    }

    It 'lists every issue folder in name order with its state' {
        $issues = Get-NbnpIssueFolders -TitleFolder $script:TitleRoot
        $issues.Name | Should -Be @('KCR_1902_16_24', 'KCR_1902_16_25', 'KCR_1902_17_26', 'KCR_1902_18_27')
        $issues.State | Should -Be @('done', 'new', 'new', 'legacy')
    }

    It 'sums them up, with the first that needs metadata' {
        $summary = Get-NbnpTitleSummary -TitleFolder $script:TitleRoot
        $summary.Total, $summary.Done, $summary.Legacy, $summary.New | Should -Be @(4, 1, 1, 2)
        Split-Path -Leaf $summary.FirstNew | Should -Be 'KCR_1902_16_25'
    }

    It 'guesses the title folder from <From> when there is no title.toml' -ForEach @(
        @{ From = 'KCR_1902\KCR_1902_17\KCR_1902_17_26' }, @{ From = 'KCR_1902\KCR_1902_17' }
        @{ From = 'KCR_1902' }, @{ From = '.' }
    ) {
        $folder = Join-Path $script:TitleRoot ($From -replace '\\', [IO.Path]::DirectorySeparatorChar)
        $found = Find-NbnpTitleFolder -Folder $folder
        $found.Exists | Should -BeFalse
        $found.Path | Should -Be $script:TitleRoot
    }

    It 'finds the title folder holding title.toml, from it or below it' {
        $file = Join-Path $script:TitleRoot 'title.toml'
        [IO.File]::WriteAllText($file, "title = `"The Kings County Record`"`n")
        try {
            foreach ($from in @($script:TitleRoot, (Join-Path $script:TitleRoot 'KCR_1902' 'KCR_1902_17' 'KCR_1902_17_26'))) {
                $found = Find-NbnpTitleFolder -Folder $from
                $found.Exists | Should -BeTrue
                $found.Path | Should -Be $script:TitleRoot
            }
        }
        finally { Remove-Item -LiteralPath $file }
    }

    It 'goes to the next issue across volume folders, skipping metadata.php issues' {
        $issues = Get-NbnpIssueFolders -TitleFolder $script:TitleRoot
        $from = Join-Path $script:TitleRoot 'KCR_1902' 'KCR_1902_16' 'KCR_1902_16_25'
        Split-Path -Leaf (Find-NbnpNextIssueFolder -IssueFolder $from -Issues $issues) | Should -Be 'KCR_1902_17_26'
        $last = Join-Path $script:TitleRoot 'KCR_1902' 'KCR_1902_17' 'KCR_1902_17_26'
        Find-NbnpNextIssueFolder -IssueFolder $last -Issues $issues | Should -BeNullOrEmpty
    }
}

Describe 'Viewing a page' {
    BeforeAll {
        # A 2000 x 3000 page in a 500 x 300 box.
        function View { New-NbnpView -ImageWidth 2000 -ImageHeight 3000 -BoxWidth 500 -BoxHeight 300 }
    }

    It 'starts at the page''s width, at the top' {
        $view = View
        $view.Scale | Should -Be 0.25
        $view.X, $view.Y | Should -Be @(0, 0)
    }

    It 'zooms in keeping the point under the pointer still' {
        $view = View
        $before = @(($view.X + 100 / $view.Scale), ($view.Y + 50 / $view.Scale))
        $view = Step-NbnpViewZoom $view 2 100 50
        $view.Scale | Should -Be 0.5
        @(($view.X + 100 / $view.Scale), ($view.Y + 50 / $view.Scale)) | Should -Be $before
    }

    It 'zooms out no further than the whole page, and in no further than 4 to 1' {
        $view = Step-NbnpViewZoom (View) 0.001 0 0
        $view.Scale | Should -Be 0.1
        $view = Step-NbnpViewZoom (View) 1000 0 0
        $view.Scale | Should -Be 4
    }

    It 'centres a page narrower than the box' {
        $view = Step-NbnpViewZoom (View) 0.001 0 0
        $view.X | Should -Be -1500
        $view.Y | Should -Be 0
    }

    It 'pans with the pointer and stops at the edges' {
        $view = Move-NbnpView (View) 0 -100
        $view.Y | Should -Be 400
        $view = Move-NbnpView $view 0 -100000
        $view.Y | Should -Be 1800
        $view = Move-NbnpView $view 100000 100000
        $view.X, $view.Y | Should -Be @(0, 0)
    }
}
