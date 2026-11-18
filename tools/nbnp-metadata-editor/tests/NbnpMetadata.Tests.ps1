#Requires -Modules @{ ModuleName = 'Pester'; ModuleVersion = '5.0' }
# Tests for NbnpMetadata.psm1: everything but the form. Run with Invoke-Pester.
#
# Set NBNP_EXPORT to a directory to also write every valid issue fixture, as the editor would
# save it, into a tree that check-with-nbnp-ingest.sh then runs nbnp-ingest check over.

BeforeAll {
    Import-Module (Join-Path $PSScriptRoot '..' 'NbnpMetadata.psm1') -Force
    $script:Fixtures = Join-Path $PSScriptRoot 'fixtures'
    $script:TitleFile = Read-NbnpMetadataFile -Folder $script:Fixtures -Kind Title
    $script:Today = [datetime]'2026-11-18'

    function Get-Fixture([string] $Kind, [string] $Verdict) {
        Get-ChildItem -LiteralPath (Join-Path $script:Fixtures $Kind $Verdict) -Filter '*.toml' |
            Sort-Object Name
    }

    function Get-Tag([string] $Text, [string] $Tag) {
        $m = [regex]::Match($Text, "(?m)^# ${Tag}: (\S+)")
        if ($m.Success) { $m.Groups[1].Value } else { $null }
    }

    # Parses and checks a fixture as the editor would on opening it.
    function Invoke-Fixture([IO.FileInfo] $File, [string] $Kind) {
        $text = [IO.File]::ReadAllText($File.FullName)
        $parsed = ConvertFrom-NbnpToml -Text $text -Kind $Kind
        $checked = $null
        if ($parsed.Supported) {
            $checked = Test-NbnpMetadata -Kind $Kind -Values $parsed.Values -Title $script:TitleFile -Today $script:Today
        }
        [pscustomobject]@{ Text = $text; Parsed = $parsed; Checked = $checked }
    }

    function Values([hashtable] $Table) {
        $values = [ordered]@{}
        foreach ($key in $Table.Keys) {
            $kind = if ($key -in @('date', 'title_id')) { 'bare' } else { 'string' }
            $values[$key] = New-NbnpValue $Table[$key] $kind
        }
        $values
    }
}

Describe 'Fixtures' {
    It 'accepts the valid <Kind> fixture <Name>' -ForEach @(
        foreach ($kind in 'Issue', 'Title') {
            foreach ($file in (Get-ChildItem (Join-Path $PSScriptRoot 'fixtures' $kind.ToLower() 'valid') -Filter *.toml)) {
                @{ Kind = $kind; Name = $file.Name; Path = $file.FullName }
            }
        }
    ) {
        $result = Invoke-Fixture (Get-Item -LiteralPath $Path) $Kind
        $result.Parsed.Reason | Should -BeNullOrEmpty
        $errors = @($result.Checked.Findings | Where-Object Severity -EQ 'Error')
        $errors.Message | Should -BeNullOrEmpty
        $warnings = @($result.Checked.Findings | Where-Object Severity -EQ 'Warning')
        $expected = Get-Tag $result.Text 'warn'
        if ($expected) { $warnings.Field | Should -Contain $expected }
        else { $warnings.Message | Should -BeNullOrEmpty }

        if ($env:NBNP_EXPORT -and $Kind -eq 'Issue') {
            $dir = Join-Path $env:NBNP_EXPORT 'News' ([IO.Path]::GetFileNameWithoutExtension($Name))
            New-Item -ItemType Directory -Force -Path $dir | Out-Null
            $toml = ConvertTo-NbnpToml -Kind Issue -Values $result.Checked.Values -Header $result.Parsed.Header
            [IO.File]::WriteAllText((Join-Path $dir 'issue.toml'), $toml)
        }
    }

    It 'refuses the invalid <Kind> fixture <Name> for <Field>' -ForEach @(
        foreach ($kind in 'Issue', 'Title') {
            foreach ($file in (Get-ChildItem (Join-Path $PSScriptRoot 'fixtures' $kind.ToLower() 'invalid') -Filter *.toml)) {
                $field = [regex]::Match([IO.File]::ReadAllText($file.FullName), '(?m)^# expect: (\S+)').Groups[1].Value
                @{ Kind = $kind; Name = $file.Name; Path = $file.FullName; Field = $field }
            }
        }
    ) {
        $Field | Should -Not -BeNullOrEmpty -Because 'each invalid fixture names its field'
        $result = Invoke-Fixture (Get-Item -LiteralPath $Path) $Kind
        if ($result.Parsed.Supported) {
            $errors = @($result.Checked.Findings | Where-Object Severity -EQ 'Error')
            $errors.Field | Should -Contain $Field
        }
        else {
            $result.Parsed.Reason | Should -Match ([regex]::Escape($Field))
        }
    }
}

Describe 'Reading' {
    It 'keeps comment lines before the first key as the header' {
        $parsed = ConvertFrom-NbnpToml -Text "# one`n# two`ndate = 1909-01-29 # inline`n# later`nvolume = `"1`"`nissue = `"1`"`n" -Kind Issue
        $parsed.Header | Should -Be @('# one', '# two')
        $parsed.Values['date'].Text | Should -Be '1909-01-29'
    }

    It 'reads basic, literal and multi-line strings' {
        $text = @'
date = 1909-01-29
volume = 'C:\no\escapes'
issue = "tab\there \"q\" \\ \u00e9"
edition = '''
two
lines'''
errata = """
one \
   two"""
'@
        $values = (ConvertFrom-NbnpToml -Text $text -Kind Issue).Values
        $values['volume'].Text | Should -Be 'C:\no\escapes'
        $values['issue'].Text | Should -Be "tab`there `"q`" \ é"
        $values['edition'].Text | Should -Be "two`nlines"
        $values['errata'].Text | Should -Be 'one two'
    }

    It 'treats a file that is not UTF-8 as unsupported' {
        $dir = Join-Path $TestDrive 'latin1'
        New-Item -ItemType Directory $dir | Out-Null
        [IO.File]::WriteAllBytes((Join-Path $dir 'issue.toml'), [byte[]](0x76, 0x6F, 0x6C, 0x3D, 0xE9))
        $read = Read-NbnpMetadataFile -Folder $dir -Kind Issue
        $read.Supported | Should -BeFalse
        $read.Reason | Should -Match 'UTF-8'
    }

    It 'reports a missing file as new' {
        $read = Read-NbnpMetadataFile -Folder $TestDrive -Kind Title
        $read.Exists | Should -BeFalse
        $read.Supported | Should -BeTrue
        $read.Hash | Should -BeNullOrEmpty
    }
}

Describe 'Checking' {
    It 'trims single-line values and respaces missing pages' {
        $checked = Test-NbnpMetadata -Kind Issue -Title $script:TitleFile -Today $script:Today -Values (
            Values @{ date = '1909-01-29'; volume = ' 33 '; issue = '5'; missing_pages = '3,6 - 8'; edition = '   ' })
        $checked.Findings | Should -BeNullOrEmpty
        $checked.Values['volume'].Text | Should -Be '33'
        $checked.Values['missing_pages'].Text | Should -Be '3, 6-8'
        $checked.Values.Contains('edition') | Should -BeFalse
    }

    It 'keeps line breaks in errata and normalises them to LF' {
        $checked = Test-NbnpMetadata -Kind Issue -Title $script:TitleFile -Today $script:Today -Values (
            Values @{ date = '1909-01-29'; volume = '33'; issue = '5'; errata = "Incorrect issue numbering`r`nTorn page   `r`n" })
        $checked.Findings | Should -BeNullOrEmpty
        $checked.Values['errata'].Text | Should -Be "Incorrect issue numbering`nTorn page"
    }

    It 'warns when the folder path names a different year' {
        $checked = Test-NbnpMetadata -Kind Issue -Title $script:TitleFile -Today $script:Today -Folder 'X:\The_News\TN_1909\TN_1909_33\TN_1909_33_05' -Values (
            Values @{ date = '1910-01-29'; volume = '33'; issue = '5' })
        $checked.Findings.Severity | Should -Be 'Warning'
        $checked.Findings.Field | Should -Be 'date'
    }

    It 'does not warn when the folder year matches' {
        $checked = Test-NbnpMetadata -Kind Issue -Title $script:TitleFile -Today $script:Today -Folder 'X:\The_News\TN_1909\TN_1909_33\TN_1909_33_05' -Values (
            Values @{ date = '1909-01-29'; volume = '33'; issue = '5' })
        $checked.Findings | Should -BeNullOrEmpty
    }

    It 'requires the title values when no title.toml is above' {
        $checked = Test-NbnpMetadata -Kind Issue -Title $null -Today $script:Today -Values (
            Values @{ date = '1909-01-29'; volume = '33'; issue = '5' })
        @($checked.Findings | Where-Object Severity -EQ 'Error').Field | Sort-Object | Should -Be @('language', 'media', 'title')
    }

    It 'checks title IDs: <Id>' -ForEach @(
        @{ Id = '48'; Ok = $true }, @{ Id = '4294967295'; Ok = $true }, @{ Id = '4294967296'; Ok = $false },
        @{ Id = '0'; Ok = $false }, @{ Id = '-1'; Ok = $false }, @{ Id = '4.8'; Ok = $false },
        @{ Id = 'abc'; Ok = $false }, @{ Id = ''; Ok = $true }
    ) {
        $checked = Test-NbnpMetadata -Kind Title -Today $script:Today -Values (
            Values @{ title_id = $Id; title = 'The News'; language = 'eng'; media = 'print' })
        (@($checked.Findings | Where-Object { $_.Field -eq 'title_id' -and $_.Severity -eq 'Error' }).Count -eq 0) |
            Should -Be $Ok
    }

    It 'saves a title without a Title ID, with a warning and no title_id line' {
        $checked = Test-NbnpMetadata -Kind Title -Today $script:Today -Values (
            Values @{ title_id = ''; title = 'The News'; language = 'eng'; media = 'print' })
        $checked.Findings.Severity | Should -Be 'Warning'
        $checked.Findings.Field | Should -Be 'title_id'
        ConvertTo-NbnpToml -Kind Title -Values $checked.Values |
            Should -Be "title = `"The News`"`nlanguage = `"eng`"`nmedia = `"print`"`n"
    }

    It 'checks dates: <Date>' -ForEach @(
        @{ Date = '1909-01-29'; Ok = $true }, @{ Date = '1908-02-29'; Ok = $true }, @{ Date = '1909-02-29'; Ok = $false },
        @{ Date = '1909-1-29'; Ok = $true }, @{ Date = '29/01/1909'; Ok = $false }, @{ Date = '2026-11-19'; Ok = $false }
    ) {
        $checked = Test-NbnpMetadata -Kind Issue -Title $script:TitleFile -Today $script:Today -Values (
            Values @{ date = $Date; volume = '33'; issue = '5' })
        (@($checked.Findings | Where-Object Severity -EQ 'Error').Count -eq 0) | Should -Be $Ok
    }
}

Describe 'Writing' {
    It 'writes keys in schema order with correct types' {
        $checked = Test-NbnpMetadata -Kind Issue -Title $script:TitleFile -Today $script:Today -Values (
            Values @{ issue = '5'; errata = 'x'; volume = '33'; date = '1909-01-29'; title_id = '49' })
        ConvertTo-NbnpToml -Kind Issue -Values $checked.Values |
            Should -Be "date = 1909-01-29`nvolume = `"33`"`nissue = `"5`"`nerrata = `"x`"`ntitle_id = 49`n"
    }

    It 'escapes quotes, backslashes and control characters, and keeps other characters' {
        $toml = ConvertTo-NbnpToml -Kind Issue -Values (Values @{ errata = "a `"b`" \ c`nd`te – é" })
        $toml | Should -Be "errata = `"a \`"b\`" \\ c\nd\te – é`"`n"
        (ConvertFrom-NbnpToml -Text $toml -Kind Issue).Values['errata'].Text | Should -Be "a `"b`" \ c`nd`te – é"
    }

    It 'round-trips the valid issue fixture <Name>' -ForEach @(
        foreach ($file in (Get-ChildItem (Join-Path $PSScriptRoot 'fixtures' 'issue' 'valid') -Filter *.toml)) {
            @{ Name = $file.Name; Path = $file.FullName }
        }
    ) {
        $first = Invoke-Fixture (Get-Item -LiteralPath $Path) Issue
        $toml = ConvertTo-NbnpToml -Kind Issue -Values $first.Checked.Values -Header $first.Parsed.Header
        $again = ConvertFrom-NbnpToml -Text $toml -Kind Issue
        $again.Supported | Should -BeTrue
        foreach ($key in $first.Checked.Values.Keys) {
            $again.Values[$key].Text | Should -Be $first.Checked.Values[$key].Text
            $again.Values[$key].Kind | Should -Be $first.Checked.Values[$key].Kind
        }
        $again.Header | Should -Be $first.Parsed.Header
    }
}

Describe 'Saving' {
    BeforeEach {
        $script:dir = Join-Path $TestDrive ([guid]::NewGuid().ToString('N'))
        New-Item -ItemType Directory -Path $script:dir | Out-Null
        $script:path = Join-Path $script:dir 'issue.toml'
    }

    It 'creates a new file in UTF-8 without a byte order mark' {
        Save-NbnpMetadataFile -Path $path -Text "edition = `"É`"`n" -ExpectedHash $null
        $bytes = [IO.File]::ReadAllBytes($path)
        $bytes[0] | Should -Be ([byte][char]'e')
        [Text.Encoding]::UTF8.GetString($bytes) | Should -Be "edition = `"É`"`n"
        @(Get-ChildItem -LiteralPath $dir -Force).Name | Should -Be @('issue.toml')
    }

    It 'replaces an unchanged file and keeps the previous version as .bak' {
        [IO.File]::WriteAllText($path, "old`n")
        $read = Read-NbnpMetadataFile -Folder $dir -Kind Issue
        Save-NbnpMetadataFile -Path $path -Text "new`n" -ExpectedHash $read.Hash
        [IO.File]::ReadAllText($path) | Should -Be "new`n"
        [IO.File]::ReadAllText("$path.bak") | Should -Be "old`n"
        @(Get-ChildItem -LiteralPath $dir -Force -Filter '*.tmp') | Should -BeNullOrEmpty
    }

    It 'refuses to overwrite a file changed since it was read' {
        [IO.File]::WriteAllText($path, "old`n")
        $read = Read-NbnpMetadataFile -Folder $dir -Kind Issue
        [IO.File]::WriteAllText($path, "someone else`n")
        { Save-NbnpMetadataFile -Path $path -Text "new`n" -ExpectedHash $read.Hash } | Should -Throw '*changed*'
        [IO.File]::ReadAllText($path) | Should -Be "someone else`n"
    }

    It 'refuses to overwrite a file created since the form opened' {
        $read = Read-NbnpMetadataFile -Folder $dir -Kind Issue
        [IO.File]::WriteAllText($path, "someone else`n")
        { Save-NbnpMetadataFile -Path $path -Text "new`n" -ExpectedHash $read.Hash } | Should -Throw '*created*'
        [IO.File]::ReadAllText($path) | Should -Be "someone else`n"
    }

    It 'leaves the original and no temporary file when the write fails' {
        [IO.File]::WriteAllText($path, "old`n")
        $read = Read-NbnpMetadataFile -Folder $dir -Kind Issue
        # A directory where the backup must go makes the replace fail.
        New-Item -ItemType Directory -Path "$path.bak" | Out-Null
        { Save-NbnpMetadataFile -Path $path -Text "new`n" -ExpectedHash $read.Hash } | Should -Throw
        [IO.File]::ReadAllText($path) | Should -Be "old`n"
        @(Get-ChildItem -LiteralPath $dir -Force -Filter '*.tmp') | Should -BeNullOrEmpty
    }
}

Describe 'The last issue saved' {
    BeforeEach {
        $script:state = Join-Path $TestDrive ([guid]::NewGuid().ToString('N'))
    }

    It 'has none before an issue is saved' {
        Get-NbnpLastIssue -Directory $state | Should -BeNullOrEmpty
    }

    It 'returns the saved values and folder, without missing pages and errata' {
        $checked = Test-NbnpMetadata -Kind Issue -Title $script:TitleFile -Today $script:Today -Values (
            Values @{ date = '1909-01-29'; volume = '33'; issue = '5'; edition = 'Evening'
                missing_pages = '3, 4'; errata = 'Incorrect issue numbering'; supplement_title = 'Supplement to The News' })
        Save-NbnpLastIssue -Values $checked.Values -Folder "X:\The News – l'été\TN_1909_33_05" -Directory $state
        $last = Get-NbnpLastIssue -Directory $state
        $last.Folder | Should -Be "X:\The News – l'été\TN_1909_33_05"
        $last.Values.Keys | Should -Be @('date', 'volume', 'issue', 'edition', 'supplement_title')
        $last.Values['date'].Text | Should -Be '1909-01-29'
        $last.Values['date'].Kind | Should -Be 'bare'
        $last.Values['supplement_title'].Text | Should -Be 'Supplement to The News'
    }

    It 'keeps only the most recent issue' {
        foreach ($issue in '5', '6') {
            Save-NbnpLastIssue -Values (Values @{ date = '1909-01-29'; volume = '33'; issue = $issue }) -Folder "X:\TN_$issue" -Directory $state
        }
        (Get-NbnpLastIssue -Directory $state).Values['issue'].Text | Should -Be '6'
    }

    It 'ignores a remembered file it cannot read' {
        New-Item -ItemType Directory -Path $state | Out-Null
        [IO.File]::WriteAllText((Join-Path $state 'last-issue.toml'), "[broken`n")
        Get-NbnpLastIssue -Directory $state | Should -BeNullOrEmpty
    }

    It 'never fails when it cannot remember' {
        $file = Join-Path $TestDrive 'a-file'
        [IO.File]::WriteAllText($file, 'x')
        { Save-NbnpLastIssue -Values (Values @{ issue = '5' }) -Folder 'X:\TN' -Directory $file } | Should -Not -Throw
    }
}

Describe 'Finding related files' {
    BeforeAll {
        $root = Join-Path $TestDrive "The News (Saint John) – l'été"
        $issue = Join-Path $root 'TN_1909' 'TN_1909_33' 'TN_1909_33_05'
        New-Item -ItemType Directory -Force -Path $issue, "${issue}_Sup", "${issue}_S2" | Out-Null
        [IO.File]::WriteAllText((Join-Path $root 'title.toml'), "title_id = 48`n")
    }

    It 'finds the nearest title.toml above, not in the issue folder itself' {
        [IO.File]::WriteAllText((Join-Path $issue 'title.toml'), "title_id = 1`n")
        Find-NbnpTitleFile -IssueFolder $issue | Should -Be (Join-Path $root 'title.toml')
        Remove-Item -LiteralPath (Join-Path $issue 'title.toml')
    }

    It 'finds a supplement''s issue folder: <Suffix>' -ForEach @(@{ Suffix = '_Sup' }, @{ Suffix = '_S2' }) {
        Find-NbnpParentIssue -IssueFolder "$issue$Suffix" | Should -Be $issue
    }

    It 'finds no parent for an ordinary issue' {
        Find-NbnpParentIssue -IssueFolder $issue | Should -BeNullOrEmpty
    }

    It 'reads the years a path names' {
        Get-NbnpPathYears 'X:\TN_1909\TN_1909_33_05' | Should -Be @(1909)
        Get-NbnpPathYears 'X:\Bruns_2003\Bruns_2003_136' | Should -Be @(2003)
        Get-NbnpPathYears 'X:\Early\EA_1650_01' | Should -Be @(1650)
        @(Get-NbnpPathYears 'X:\Agenutemagen\AG_01') | Should -BeNullOrEmpty
    }
}

Describe 'Scripts' {
    It '<Name> is ASCII, which Windows PowerShell 5.1 reads correctly without a byte order mark' -ForEach @(
        foreach ($file in (Get-ChildItem (Join-Path $PSScriptRoot '..') -Filter '*.ps*1' | Where-Object Extension -In '.ps1', '.psm1')) {
            @{ Name = $file.Name; Path = $file.FullName }
        }
    ) {
        $bytes = [IO.File]::ReadAllBytes($Path)
        @($bytes | Where-Object { $_ -gt 127 }).Count | Should -Be 0
    }

    It '<Name> parses' -ForEach @(
        foreach ($file in (Get-ChildItem (Join-Path $PSScriptRoot '..') -Filter '*.ps*1' | Where-Object Extension -In '.ps1', '.psm1')) {
            @{ Name = $file.Name; Path = $file.FullName }
        }
    ) {
        $tokens = $null
        $errors = $null
        [void][System.Management.Automation.Language.Parser]::ParseFile($Path, [ref]$tokens, [ref]$errors)
        $errors | Should -BeNullOrEmpty
    }
}
