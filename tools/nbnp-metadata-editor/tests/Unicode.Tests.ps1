# Text beyond accented Latin: characters outside the Basic Multilingual Plane (two UTF-16 code
# units each), combining accents, right-to-left and CJK scripts, and invisible characters that
# arrive with pasted text.

BeforeAll {
    Import-Module (Join-Path $PSScriptRoot '..' 'NbnpMetadata.psm1') -Force
    $script:TitleFile = Read-NbnpMetadataFile -Folder (Join-Path $PSScriptRoot 'fixtures') -Kind Title
    $script:Today = [datetime]'2026-11-18'

    function U([int[]] $CodePoints) { -join ($CodePoints | ForEach-Object { [char]::ConvertFromUtf32($_) }) }

    function Check([hashtable] $Strings) {
        $values = [ordered]@{ date = New-NbnpValue '1909-01-29' 'bare'; volume = New-NbnpValue '33'; issue = New-NbnpValue '5' }
        foreach ($key in $Strings.Keys) { $values[$key] = New-NbnpValue $Strings[$key] }
        Test-NbnpMetadata -Kind Issue -Values $values -Title $script:TitleFile -Today $script:Today
    }

    $script:Samples = @{
        'outside the BMP'   = (U 0x1D504, 0x1F4F0, 0x2070E)        # 𝔄 📰 𠜎
        'Mi''kmaq and Wolastoqey' = "Wolastoqey $(U 0x02BC)t ŭ Mi$(U 0x02BC)kmaq"
        'right to left'     = (U 0x062C, 0x0631, 0x064A, 0x062F, 0x0629)   # جريدة
        'CJK'               = (U 0x65B0, 0x805E)                     # 新聞
        'typographic'       = "$(U 0x201C)The News$(U 0x201D) $(U 0x2018)06 $(U 0x2014) no. 5"
        'combined accent'   = "Caf$(U 0x00E9)"
    }
}

Describe 'Unicode text' {
    It 'writes and reads back <Name> unchanged' -ForEach @(
        @{ Name = 'outside the BMP' }, @{ Name = 'Mi''kmaq and Wolastoqey' }, @{ Name = 'right to left' },
        @{ Name = 'CJK' }, @{ Name = 'typographic' }, @{ Name = 'combined accent' }
    ) {
        $text = $script:Samples[$Name]
        $checked = Check @{ edition = $text; errata = "$text`n$text" }
        @($checked.Findings | Where-Object Severity -EQ 'Error').Message | Should -BeNullOrEmpty
        $toml = ConvertTo-NbnpToml -Kind Issue -Values $checked.Values
        $again = ConvertFrom-NbnpToml -Text $toml -Kind Issue
        $again.Values['edition'].Text | Should -BeExactly $text
        $again.Values['errata'].Text | Should -BeExactly "$text`n$text"

        $dir = Join-Path $TestDrive ([guid]::NewGuid().ToString('N'))
        New-Item -ItemType Directory -Path $dir | Out-Null
        Save-NbnpMetadataFile -Path (Join-Path $dir 'issue.toml') -Text $toml -ExpectedHash $null
        $read = Read-NbnpMetadataFile -Folder $dir -Kind Issue
        $read.Supported | Should -BeTrue
        $read.Values['edition'].Text | Should -BeExactly $text
    }

    It 'reads \U escapes outside the BMP' {
        $parsed = ConvertFrom-NbnpToml -Text 'edition = "\U0001F4F0 é"' -Kind Issue
        $parsed.Values['edition'].Text | Should -BeExactly "$(U 0x1F4F0) é"
    }

    It 'counts characters, not UTF-16 units, against the site''s limits' {
        $volume = (U 0x1D504) * 32
        $volume.Length | Should -Be 64
        @((Check @{ volume = $volume }).Findings | Where-Object Field -EQ 'volume') | Should -BeNullOrEmpty
        @((Check @{ volume = $volume + 'x' }).Findings | Where-Object Field -EQ 'volume').Message |
            Should -Match '33 characters'
    }

    It 'stores a decomposed accent composed, as typed on Windows' {
        $decomposed = "Cafe$(U 0x0301)"
        $stored = (Check @{ edition = $decomposed }).Values['edition'].Text
        # -BeExactly compares culturally, which counts the two forms as equal: compare ordinally.
        [string]::Equals($stored, "Caf$(U 0x00E9)", [StringComparison]::Ordinal) | Should -BeTrue
        $stored.Length | Should -Be 4
    }

    It 'trims no-break spaces at either end' {
        $nbsp = U 0x00A0
        (Check @{ edition = "${nbsp}Evening$nbsp" }).Values['edition'].Text | Should -BeExactly 'Evening'
    }

    It 'warns about invisible <Name>' -ForEach @(
        @{ Name = 'zero-width space'; Code = 0x200B }, @{ Name = 'byte order mark'; Code = 0xFEFF },
        @{ Name = 'right-to-left override'; Code = 0x202E }, @{ Name = 'soft hyphen'; Code = 0x00AD }
    ) {
        $finding = @((Check @{ edition = "Even$(U $Code)ing" }).Findings | Where-Object Field -EQ 'edition')
        $finding.Severity | Should -Be 'Warning'
        $finding.Message | Should -Match ('U\+{0:X4}' -f $Code)
    }

    It 'refuses C1 control characters' {
        $finding = @((Check @{ edition = "Even$(U 0x0085)ing" }).Findings | Where-Object Field -EQ 'edition')
        $finding.Severity | Should -Be 'Error'
    }

    It 'opens a file with a raw C1 character, which TOML allows, so it can be corrected' {
        $parsed = ConvertFrom-NbnpToml -Text "edition = `"Even$(U 0x0085)ing`"" -Kind Issue
        $parsed.Supported | Should -BeTrue
    }

    It 'refuses a C0 control character written raw in a string, as TOML does' {
        (ConvertFrom-NbnpToml -Text "edition = `"Even$(U 0x0001)ing`"" -Kind Issue).Supported | Should -BeFalse
    }
}
