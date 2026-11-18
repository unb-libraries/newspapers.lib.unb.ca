#Requires -Version 5.1
<#
.SYNOPSIS
    Reads, checks and writes NBNP title.toml and issue.toml files.

.DESCRIPTION
    The schema is the one nbnp-ingest reads (tools/nbnp-ingest/src/domain/metadata.rs); this
    module does not extend it. The checks add the cataloguing guidance staff already follow
    (values as printed, no zero padding, missing pages as numbers and ranges) on top of what
    nbnp-ingest refuses, so anything saved here also passes nbnp-ingest check.

    Values are passed around as objects with two properties:
      Text  the value as typed or as written in the file, without quotes
      Kind  'string' for a quoted TOML string, 'bare' for an unquoted date or number
#>

Set-StrictMode -Version 2.0

$script:Schemas = @{
    Title = @{
        FileName = 'title.toml'
        # Key order of the written file.
        Keys     = @('title_id', 'title', 'language', 'media')
        Required = @('title_id', 'title', 'language', 'media')
    }
    Issue = @{
        FileName = 'issue.toml'
        # The order nbnp-ingest convert writes: own values, then overrides of title.toml.
        Keys     = @('date', 'volume', 'issue', 'edition', 'supplement_title', 'missing_pages',
                     'errata', 'title', 'language', 'media', 'title_id')
        Required = @('date', 'volume', 'issue')
    }
}

$script:Languages = @('eng', 'fre')

# The longest value, in characters, the site stores (nbnp-ingest metadata::limits).
$script:MaxLength = @{
    title            = 256
    supplement_title = 256
    volume           = 32
    issue            = 32
    edition          = 128
    missing_pages    = 128
    media            = 50
}

# Earlier dates are allowed but warned about: nothing in the collection is older.
$script:EarliestLikelyYear = 1750

$script:Utf8NoBom = New-Object System.Text.UTF8Encoding($false)

function New-NbnpValue {
    <#
    .SYNOPSIS
        A value: its text and whether it is a quoted string or a bare date or number.
    #>
    param(
        [AllowEmptyString()][string] $Text,
        [ValidateSet('string', 'bare')][string] $Kind = 'string'
    )
    [pscustomobject]@{ Text = $Text; Kind = $Kind }
}

function Get-NbnpSchema {
    <#
    .SYNOPSIS
        The file name, key order and required keys of a title or issue file.
    #>
    param([Parameter(Mandatory)][ValidateSet('Title', 'Issue')][string] $Kind)
    $script:Schemas[$Kind]
}

function Get-NbnpLanguages { $script:Languages }

#region Reading

function ConvertFrom-NbnpToml {
    <#
    .SYNOPSIS
        Parses the TOML subset these files use.

    .DESCRIPTION
        Accepts blank lines, comments, and one key = value per line, where the value is a
        basic, literal or multi-line string, or a bare date or number. Anything else (tables,
        arrays, dotted or quoted keys, keys the schema does not have, a key given twice) makes
        the file unsupported: the editor then shows it read-only rather than lose content.

        Returns Supported, Reason, Values (an ordered table of key to value) and Header (the
        comment lines before the first key, kept when the file is saved).
    #>
    param(
        [Parameter(Mandatory)][AllowEmptyString()][string] $Text,
        [Parameter(Mandatory)][ValidateSet('Title', 'Issue')][string] $Kind
    )
    $schema = $script:Schemas[$Kind]
    $values = [ordered]@{}
    $header = New-Object System.Collections.Generic.List[string]
    $result = { param($reason) [pscustomobject]@{
            Supported = ($null -eq $reason); Reason = $reason; Values = $values
            Header = $header.ToArray()
        } }

    $text = $Text -replace "`r`n", "`n"
    if ($text.Length -gt 0 -and $text[0] -eq [char]0xFEFF) { $text = $text.Substring(1) }
    $pos = 0
    $line = 1
    $seenKey = $false

    while ($pos -lt $text.Length) {
        # One logical line: skip leading blanks.
        while ($pos -lt $text.Length -and ($text[$pos] -eq ' ' -or $text[$pos] -eq "`t")) { $pos++ }
        if ($pos -ge $text.Length) { break }
        $c = $text[$pos]
        if ($c -eq "`n") { $pos++; $line++; continue }
        if ($c -eq '#') {
            $end = $text.IndexOf("`n", $pos)
            if ($end -lt 0) { $end = $text.Length }
            if (-not $seenKey) { $header.Add($text.Substring($pos, $end - $pos)) }
            $pos = $end
            continue
        }
        if ($c -eq '[') { return & $result "line ${line}: tables ([...]) are not used in these files" }

        $m = [regex]::Match($text.Substring($pos), '^([A-Za-z0-9_-]+)[ \t]*=[ \t]*')
        if (-not $m.Success) {
            return & $result "line ${line}: expected key = value"
        }
        $key = $m.Groups[1].Value
        if ($schema.Keys -notcontains $key) {
            return & $result "line ${line}: $key is not a key of $($schema.FileName)"
        }
        if ($values.Contains($key)) {
            return & $result "line ${line}: $key is given twice"
        }
        $pos += $m.Length
        $seenKey = $true

        $parsed = Read-TomlValue -Text $text -Position $pos
        if ($parsed.Error) { return & $result "line ${line}: ${key}: $($parsed.Error)" }
        $values[$key] = $parsed.Value
        $line += ($text.Substring($pos, $parsed.Position - $pos).Split("`n").Count - 1)
        $pos = $parsed.Position

        # Only blanks and a comment may follow on the line.
        $rest = [regex]::Match($text.Substring($pos), '^[ \t]*(#[^\n]*)?(\n|$)')
        if (-not $rest.Success) {
            return & $result "line ${line}: ${key}: unexpected text after the value"
        }
        $pos += $rest.Length
        if ($rest.Groups[2].Value -eq "`n") { $line++ }
    }
    & $result $null
}

function Read-TomlValue {
    # Reads one value starting at Position. Returns Value, Position and Error.
    param([string] $Text, [int] $Position)
    $fail = { param($message) [pscustomobject]@{ Value = $null; Position = $Position; Error = $message } }
    $ok = { param($value, $at) [pscustomobject]@{ Value = $value; Position = $at; Error = $null } }
    $p = $Position
    $rest = $Text.Substring($p)

    if ($rest.StartsWith('"""')) {
        $p += 3
        if ($p -lt $Text.Length -and $Text[$p] -eq "`n") { $p++ }
        $sb = New-Object System.Text.StringBuilder
        while ($true) {
            if ($p -ge $Text.Length) { return & $fail 'the """ string is not closed' }
            if ($Text.Substring($p).StartsWith('"""')) {
                # Up to two quotes may sit just before the closing delimiter.
                $extra = 0
                while ($extra -lt 2 -and $p + 3 + $extra -lt $Text.Length -and $Text[$p + 3 + $extra] -eq '"') { $extra++ }
                [void]$sb.Append([string]::new('"', $extra))
                return & $ok (New-NbnpValue $sb.ToString() 'string') ($p + 3 + $extra)
            }
            $ch = $Text[$p]
            if ($ch -eq '\') {
                # A backslash at the end of a line joins the next non-blank text.
                $join = [regex]::Match($Text.Substring($p), '^\\[ \t]*\n[ \t\n]*')
                if ($join.Success) { $p += $join.Length; continue }
                $escape = Read-TomlEscape -Text $Text -Position $p
                if ($escape.Error) { return & $fail $escape.Error }
                [void]$sb.Append($escape.Value); $p = $escape.Position; continue
            }
            if ([char]::IsControl($ch) -and $ch -ne "`n" -and $ch -ne "`t") {
                return & $fail 'control characters must be escaped'
            }
            [void]$sb.Append($ch); $p++
        }
    }
    if ($rest.StartsWith("'''")) {
        $p += 3
        if ($p -lt $Text.Length -and $Text[$p] -eq "`n") { $p++ }
        $end = $Text.IndexOf("'''", $p)
        if ($end -lt 0) { return & $fail "the ''' string is not closed" }
        $extra = 0
        while ($extra -lt 2 -and $end + 3 + $extra -lt $Text.Length -and $Text[$end + 3 + $extra] -eq "'") { $extra++ }
        $value = $Text.Substring($p, $end - $p) + [string]::new("'", $extra)
        return & $ok (New-NbnpValue $value 'string') ($end + 3 + $extra)
    }
    if ($rest.StartsWith('"')) {
        $p++
        $sb = New-Object System.Text.StringBuilder
        while ($true) {
            if ($p -ge $Text.Length -or $Text[$p] -eq "`n") { return & $fail 'the string is not closed on its line' }
            $ch = $Text[$p]
            if ($ch -eq '"') { return & $ok (New-NbnpValue $sb.ToString() 'string') ($p + 1) }
            if ($ch -eq '\') {
                $escape = Read-TomlEscape -Text $Text -Position $p
                if ($escape.Error) { return & $fail $escape.Error }
                [void]$sb.Append($escape.Value); $p = $escape.Position; continue
            }
            if ([char]::IsControl($ch) -and $ch -ne "`t") { return & $fail 'control characters must be escaped' }
            [void]$sb.Append($ch); $p++
        }
    }
    if ($rest.StartsWith("'")) {
        $end = $Text.IndexOf("'", $p + 1)
        $nl = $Text.IndexOf("`n", $p + 1)
        if ($end -lt 0 -or ($nl -ge 0 -and $nl -lt $end)) { return & $fail 'the string is not closed on its line' }
        return & $ok (New-NbnpValue $Text.Substring($p + 1, $end - $p - 1) 'string') ($end + 1)
    }
    # A bare value: a date, number or boolean. Kept as text; validation decides if it fits.
    $bare = [regex]::Match($rest, '^[0-9A-Za-z+_.:-]+')
    if ($bare.Success) {
        return & $ok (New-NbnpValue $bare.Value 'bare') ($p + $bare.Length)
    }
    if ($rest.StartsWith('[') -or $rest.StartsWith('{')) {
        return & $fail 'lists and tables are not used in these files'
    }
    & $fail 'expected a value'
}

function Read-TomlEscape {
    # Reads the escape sequence at Position (the backslash). Returns Value, Position, Error.
    param([string] $Text, [int] $Position)
    $next = if ($Position + 1 -lt $Text.Length) { [string]$Text[$Position + 1] } else { '' }
    $simple = @{ 'b' = "`b"; 't' = "`t"; 'n' = "`n"; 'f' = "`f"; 'r' = "`r"; '"' = '"'; '\' = '\' }
    if ($simple.ContainsKey($next) -and $next -cmatch '^[btnfr"\\]$') {
        return [pscustomobject]@{ Value = $simple[$next]; Position = $Position + 2; Error = $null }
    }
    foreach ($form in @(@{ Letter = 'u'; Digits = 4 }, @{ Letter = 'U'; Digits = 8 })) {
        if ($next -ceq $form.Letter) {
            $hex = [regex]::Match($Text.Substring($Position + 2), "^[0-9A-Fa-f]{$($form.Digits)}")
            if (-not $hex.Success) { break }
            $code = [Convert]::ToInt32($hex.Value, 16)
            if ($code -gt 0x10FFFF -or ($code -ge 0xD800 -and $code -le 0xDFFF)) { break }
            return [pscustomobject]@{
                Value = [char]::ConvertFromUtf32($code); Position = $Position + 2 + $form.Digits; Error = $null
            }
        }
    }
    [pscustomobject]@{ Value = $null; Position = $Position; Error = "\$next is not a TOML escape" }
}

function Read-NbnpMetadataFile {
    <#
    .SYNOPSIS
        Reads the title.toml or issue.toml in a folder.

    .DESCRIPTION
        Returns Path, Exists, Hash (of the bytes read, to detect a change before saving), Text,
        and what ConvertFrom-NbnpToml returns. A file that is not valid UTF-8 is unsupported.
    #>
    param(
        [Parameter(Mandatory)][string] $Folder,
        [Parameter(Mandatory)][ValidateSet('Title', 'Issue')][string] $Kind
    )
    $path = Join-Path $Folder $script:Schemas[$Kind].FileName
    if (-not (Test-Path -LiteralPath $path -PathType Leaf)) {
        return [pscustomobject]@{
            Path = $path; Exists = $false; Hash = $null; Text = ''; Supported = $true; Reason = $null
            Values = [ordered]@{}; Header = @()
        }
    }
    $bytes = [IO.File]::ReadAllBytes($path)
    $hash = Get-BytesHash $bytes
    $strict = New-Object System.Text.UTF8Encoding($false, $true)
    try {
        $text = $strict.GetString($bytes)
    }
    catch {
        return [pscustomobject]@{
            Path = $path; Exists = $true; Hash = $hash; Text = $script:Utf8NoBom.GetString($bytes)
            Supported = $false; Reason = 'the file is not UTF-8 text'; Values = [ordered]@{}; Header = @()
        }
    }
    $parsed = ConvertFrom-NbnpToml -Text $text -Kind $Kind
    [pscustomobject]@{
        Path = $path; Exists = $true; Hash = $hash; Text = $text; Supported = $parsed.Supported
        Reason = $parsed.Reason; Values = $parsed.Values; Header = $parsed.Header
    }
}

function Get-BytesHash([byte[]] $Bytes) {
    $sha = [Security.Cryptography.SHA256]::Create()
    try { [BitConverter]::ToString($sha.ComputeHash($Bytes)) -replace '-', '' }
    finally { $sha.Dispose() }
}

function Find-NbnpTitleFile {
    <#
    .SYNOPSIS
        The title.toml an issue folder inherits from: the nearest one in its parent folder or
        above, as nbnp-ingest finds it. $null when there is none.
    #>
    param([Parameter(Mandatory)][string] $IssueFolder)
    $dir = [IO.Directory]::GetParent([IO.Path]::GetFullPath($IssueFolder).TrimEnd('\', '/'))
    while ($null -ne $dir) {
        $candidate = Join-Path $dir.FullName 'title.toml'
        if (Test-Path -LiteralPath $candidate -PathType Leaf) { return $candidate }
        $dir = $dir.Parent
    }
    $null
}

function Find-NbnpParentIssue {
    <#
    .SYNOPSIS
        For a supplement folder named like its issue plus a suffix (TN_1909_33_05_Sup,
        _Sup2, _S1), the issue folder beside it, if it exists. $null otherwise.
    #>
    param([Parameter(Mandatory)][string] $IssueFolder)
    $full = [IO.Path]::GetFullPath($IssueFolder).TrimEnd('\', '/')
    $name = Split-Path -Leaf $full
    $m = [regex]::Match($name, '^(.+?)_(Sup[A-Za-z0-9]*|S\d+)$', 'IgnoreCase')
    if (-not $m.Success) { return $null }
    $parent = Join-Path (Split-Path -Parent $full) $m.Groups[1].Value
    if (Test-Path -LiteralPath $parent -PathType Container) { return $parent }
    $null
}

#endregion

#region Checking

function Test-NbnpMetadata {
    <#
    .SYNOPSIS
        Checks the values of a title or issue file.

    .DESCRIPTION
        Values is a table of key to value object (New-NbnpValue); empty optional values may be
        left out. Single-line values are trimmed and missing pages are respaced first, so the
        form need not do it; the cleaned values are returned as Values, ready to save.

        Returns Values and Findings, each finding with Field, Severity (Error or Warning) and
        Message. Error findings block saving.

        For an issue, Title is the parsed title.toml it inherits from (or $null), and Folder
        the issue folder, whose path is compared with the date's year.
    #>
    param(
        [Parameter(Mandatory)][ValidateSet('Title', 'Issue')][string] $Kind,
        [Parameter(Mandatory)][System.Collections.IDictionary] $Values,
        $Title = $null,
        [string] $Folder = '',
        [datetime] $Today = (Get-Date).Date
    )
    $schema = $script:Schemas[$Kind]
    $findings = New-Object System.Collections.Generic.List[object]
    $error_ = { param($field, $message) $findings.Add([pscustomobject]@{ Field = $field; Severity = 'Error'; Message = $message }) }
    $warn = { param($field, $message) $findings.Add([pscustomobject]@{ Field = $field; Severity = 'Warning'; Message = $message }) }

    # Clean: trim single-line values, normalise line ends, drop empty optional values.
    $clean = [ordered]@{}
    foreach ($key in $schema.Keys) {
        if (-not $Values.Contains($key) -or $null -eq $Values[$key]) { continue }
        $value = $Values[$key]
        $text = [string]$value.Text
        if ($key -eq 'errata') {
            $text = ($text -replace "`r`n", "`n").TrimEnd()
        }
        else {
            $text = $text.Trim()
        }
        if ($key -eq 'missing_pages' -and $text -ne '') {
            $text = (($text -split ',') | ForEach-Object { ($_.Trim() -replace '\s*-\s*', '-') }) -join ', '
        }
        if ($text -eq '' -and $value.Kind -eq 'string' -and $schema.Required -notcontains $key) { continue }
        $clean[$key] = New-NbnpValue $text $value.Kind
    }

    foreach ($key in $schema.Required) {
        if (-not $clean.Contains($key) -or $clean[$key].Text -eq '') {
            & $error_ $key "$(Get-FieldLabel $key) is required."
        }
    }

    foreach ($key in @($clean.Keys)) {
        $value = $clean[$key]
        $text = $value.Text
        if ($text -eq '') { continue }
        switch ($key) {
            'title_id' {
                $number = [int64]0
                if ($text -notmatch '^[1-9][0-9]*$' -or -not [int64]::TryParse($text, [ref]$number) -or $number -gt 4294967295) {
                    & $error_ $key 'Title ID must be a whole number, 1 or more (the number in the title''s address, /serials/browse/48).'
                }
                elseif ($value.Kind -ne 'bare') {
                    & $error_ $key 'Title ID must be a number without quotes.'
                }
            }
            'date' {
                $date = [datetime]::MinValue
                $isDate = $text -match '^\d{4}-\d{2}-\d{2}$' -and
                    [datetime]::TryParseExact($text, 'yyyy-MM-dd', [Globalization.CultureInfo]::InvariantCulture,
                        [Globalization.DateTimeStyles]::None, [ref]$date)
                if (-not $isDate) {
                    & $error_ $key "Date must be a real date written YYYY-MM-DD, such as 1909-01-29; '$text' is not."
                }
                elseif ($value.Kind -ne 'bare') {
                    & $error_ $key 'Date must be written without quotes.'
                }
                elseif ($date -gt $Today) {
                    & $error_ $key "Date $text is in the future."
                }
                else {
                    if ($date.Year -lt $script:EarliestLikelyYear) {
                        & $warn $key "Date $text is before $($script:EarliestLikelyYear); check the year."
                    }
                    $years = Get-NbnpPathYears $Folder
                    if ($years.Count -gt 0 -and $years -notcontains $date.Year) {
                        & $warn $key "The folder path names $($years -join ', '), but the date is in $($date.Year)."
                    }
                }
            }
            'language' {
                if ($script:Languages -notcontains $text) {
                    & $error_ $key "Language must be one of $($script:Languages -join ', ')."
                }
            }
            default {
                if ($value.Kind -ne 'string') {
                    & $error_ $key "$(Get-FieldLabel $key) must be text in quotes."
                }
            }
        }
        if ($key -ne 'errata' -and $text -match '[\x00-\x1F\x7F]') {
            & $error_ $key "$(Get-FieldLabel $key) must be a single line."
        }
        if ($key -eq 'errata' -and $text -match '[\x00-\x08\x0B-\x1F\x7F]') {
            & $error_ $key 'Errata contains a control character; retype it.'
        }
        if ($script:MaxLength.ContainsKey($key) -and $text.Length -gt $script:MaxLength[$key]) {
            & $error_ $key "$(Get-FieldLabel $key) is $($text.Length) characters; the site stores at most $($script:MaxLength[$key])."
        }
    }

    foreach ($key in @('volume', 'issue')) {
        if (-not $clean.Contains($key)) { continue }
        $text = $clean[$key].Text
        if ($text -match '^0[0-9]') {
            & $error_ $key "$(Get-FieldLabel $key) '$text' is padded with zeros; write it as printed without them (5, not 05)."
        }
        elseif ($text -match '^ISSUE_|^[A-Z]+_[A-Z_]+$') {
            & $error_ $key "$(Get-FieldLabel $key) '$text' is a template placeholder, not a printed value."
        }
    }

    if ($clean.Contains('missing_pages') -and -not (Test-MissingPages $clean['missing_pages'].Text)) {
        & $error_ 'missing_pages' 'Missing pages must be page numbers or ranges separated by commas, such as 3, 6, 7 or 4-8: numbers, dashes and commas only, without zero padding.'
    }

    if ($Kind -eq 'Issue') {
        # Without a title.toml above, the issue must give the title's values itself.
        foreach ($key in @('title', 'language', 'media')) {
            $inherited = $null -ne $Title -and $Title.Values.Contains($key)
            if (-not $inherited -and -not $clean.Contains($key)) {
                & $error_ $key "$(Get-FieldLabel $key) is required: no title.toml above this folder provides it."
            }
        }
    }

    [pscustomobject]@{ Values = $clean; Findings = $findings.ToArray() }
}

function Test-MissingPages([string] $Text) {
    foreach ($item in ($Text -split ', ')) {
        $m = [regex]::Match($item, '^([1-9][0-9]*)(?:-([1-9][0-9]*))?$')
        if (-not $m.Success) { return $false }
        if ($m.Groups[2].Success -and [int64]$m.Groups[2].Value -le [int64]$m.Groups[1].Value) { return $false }
    }
    $true
}

function Get-NbnpPathYears {
    <#
    .SYNOPSIS
        The four-digit years (1700-2099) a folder path names, such as 1909 in TN_1909_33_05.
    #>
    param([string] $Folder)
    $years = @()
    if (-not $Folder) { return , $years }
    foreach ($m in [regex]::Matches($Folder, '(?<![0-9])(1[78][0-9]{2}|19[0-9]{2}|20[0-9]{2})(?![0-9])')) {
        $year = [int]$m.Value
        if ($years -notcontains $year) { $years += $year }
    }
    , $years
}

function Get-FieldLabel([string] $Key) {
    $labels = @{
        title_id = 'Title ID'; title = 'Title'; language = 'Language'; media = 'Media'; date = 'Date'
        volume = 'Volume'; issue = 'Issue'; edition = 'Edition'; supplement_title = 'Supplement title'
        missing_pages = 'Missing pages'; errata = 'Errata'
    }
    if ($labels.ContainsKey($Key)) { $labels[$Key] } else { $Key }
}

#endregion

#region Writing

function ConvertTo-NbnpToml {
    <#
    .SYNOPSIS
        Writes checked values as TOML, in the schema's key order, keeping a header comment.
    #>
    param(
        [Parameter(Mandatory)][ValidateSet('Title', 'Issue')][string] $Kind,
        [Parameter(Mandatory)][System.Collections.IDictionary] $Values,
        [string[]] $Header = @()
    )
    $sb = New-Object System.Text.StringBuilder
    foreach ($line in $Header) { [void]$sb.Append($line).Append("`n") }
    foreach ($key in $script:Schemas[$Kind].Keys) {
        if (-not $Values.Contains($key)) { continue }
        $value = $Values[$key]
        $written = if ($value.Kind -eq 'bare') { $value.Text } else { ConvertTo-TomlString $value.Text }
        [void]$sb.Append("$key = $written`n")
    }
    $sb.ToString()
}

function ConvertTo-TomlString([string] $Text) {
    # A basic string: quotes, backslashes and control characters escaped, all else kept.
    $sb = New-Object System.Text.StringBuilder
    [void]$sb.Append('"')
    foreach ($ch in $Text.ToCharArray()) {
        switch -CaseSensitive ($ch) {
            '"' { [void]$sb.Append('\"'); continue }
            '\' { [void]$sb.Append('\\'); continue }
            "`n" { [void]$sb.Append('\n'); continue }
            "`t" { [void]$sb.Append('\t'); continue }
            "`r" { [void]$sb.Append('\r'); continue }
            default {
                if ([char]::IsControl($ch)) { [void]$sb.Append(('\u{0:X4}' -f [int]$ch)) }
                else { [void]$sb.Append($ch) }
            }
        }
    }
    [void]$sb.Append('"')
    $sb.ToString()
}

function Save-NbnpMetadataFile {
    <#
    .SYNOPSIS
        Writes a metadata file safely.

    .DESCRIPTION
        Refuses when the file changed since it was read (ExpectedHash, $null for a new file),
        writes a temporary file in the same folder, then replaces the target, keeping the
        previous version as <name>.bak. On failure the original is left as it was.
    #>
    param(
        [Parameter(Mandatory)][string] $Path,
        [Parameter(Mandatory)][string] $Text,
        [AllowNull()][string] $ExpectedHash
    )
    # A [string] parameter turns $null into '', so both mean "there was no file".
    $expected = if ([string]::IsNullOrEmpty($ExpectedHash)) { $null } else { $ExpectedHash }
    $exists = Test-Path -LiteralPath $Path -PathType Leaf
    $current = if ($exists) { Get-BytesHash ([IO.File]::ReadAllBytes($Path)) } else { $null }
    if ($current -ne $expected) {
        if ($null -eq $expected) {
            throw "$(Split-Path -Leaf $Path) was created by someone else while this form was open. Close the form and open it again to see it."
        }
        throw "$(Split-Path -Leaf $Path) was changed or removed while this form was open. Close the form and open it again to see the current version."
    }
    $folder = Split-Path -Parent $Path
    $temp = Join-Path $folder ('.' + (Split-Path -Leaf $Path) + '.' + [guid]::NewGuid().ToString('N') + '.tmp')
    try {
        [IO.File]::WriteAllText($temp, $Text, $script:Utf8NoBom)
        if ($exists) {
            [IO.File]::Replace($temp, $Path, "$Path.bak")
        }
        else {
            [IO.File]::Move($temp, $Path)
        }
    }
    finally {
        if (Test-Path -LiteralPath $temp) { Remove-Item -LiteralPath $temp -Force -ErrorAction SilentlyContinue }
    }
}

#endregion

Export-ModuleMember -Function New-NbnpValue, Get-NbnpSchema, Get-NbnpLanguages, ConvertFrom-NbnpToml,
    Read-NbnpMetadataFile, Find-NbnpTitleFile, Find-NbnpParentIssue, Test-NbnpMetadata,
    Get-NbnpPathYears, ConvertTo-NbnpToml, Save-NbnpMetadataFile
