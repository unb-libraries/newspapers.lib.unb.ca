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
        # The Title ID may not be known yet when the file is made; nbnp-ingest refuses to import
        # without it, and the editor warns.
        Required = @('title', 'language', 'media')
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
$script:EarliestLikelyYear = 1600

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
            if (Test-TomlForbidden $ch -Multiline) { return & $fail 'control characters must be escaped' }
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
        foreach ($ch in $value.ToCharArray()) {
            if (Test-TomlForbidden $ch -Multiline) { return & $fail 'literal strings cannot hold control characters' }
        }
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
            if (Test-TomlForbidden $ch) { return & $fail 'control characters must be escaped' }
            [void]$sb.Append($ch); $p++
        }
    }
    if ($rest.StartsWith("'")) {
        $end = $Text.IndexOf("'", $p + 1)
        $nl = $Text.IndexOf("`n", $p + 1)
        if ($end -lt 0 -or ($nl -ge 0 -and $nl -lt $end)) { return & $fail 'the string is not closed on its line' }
        $value = $Text.Substring($p + 1, $end - $p - 1)
        foreach ($ch in $value.ToCharArray()) {
            if (Test-TomlForbidden $ch) { return & $fail 'literal strings cannot hold control characters' }
        }
        return & $ok (New-NbnpValue $value 'string') ($end + 1)
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

function Test-TomlForbidden([char] $Ch, [switch] $Multiline) {
    # TOML refuses control characters U+0000-U+001F and U+007F written raw in a string, except a
    # tab, and a line break in a multi-line string. Other characters, C1 controls included, may
    # be written as they are.
    $code = [int]$Ch
    if ($code -eq 0x09 -or ($Multiline -and $code -eq 0x0A)) { return $false }
    $code -le 0x1F -or $code -eq 0x7F
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

function Get-NbnpNaturalKey {
    <#
    .SYNOPSIS
        A sort key that orders names the way people read them: TN_1909_33_9 before TN_1909_33_10.
    #>
    param([string] $Name)
    [regex]::Replace($Name.ToLowerInvariant(), '\d+', { param($m) $m.Value.PadLeft(12, '0') })
}

function Get-NbnpIssueFolders {
    <#
    .SYNOPSIS
        Every issue folder under a title folder, in name order: Path, Name and State, which is
        'done' (has issue.toml), 'legacy' (has metadata.php only) or 'new' (has neither).

    .DESCRIPTION
        An issue folder is one holding page images. Its own subfolders are not searched.
    #>
    param([Parameter(Mandatory)][string] $TitleFolder)
    $found = New-Object System.Collections.Generic.List[object]
    Add-IssueFolders -Folder $TitleFolder -Found $found
    , $found.ToArray()
}

function Add-IssueFolders([string] $Folder, [System.Collections.Generic.List[object]] $Found) {
    if (Get-NbnpFirstPage -Folder $Folder) {
        $state = if (Test-Path -LiteralPath (Join-Path $Folder 'issue.toml') -PathType Leaf) { 'done' }
        elseif (Test-Path -LiteralPath (Join-Path $Folder 'metadata.php') -PathType Leaf) { 'legacy' }
        else { 'new' }
        $Found.Add([pscustomobject]@{ Path = $Folder; Name = (Split-Path -Leaf $Folder); State = $state })
        return
    }
    $children = @(Get-ChildItem -LiteralPath $Folder -Directory -ErrorAction SilentlyContinue |
            Where-Object { -not $_.Name.StartsWith('.') } | Sort-Object { Get-NbnpNaturalKey $_.Name })
    foreach ($child in $children) { Add-IssueFolders -Folder $child.FullName -Found $Found }
}

function Get-NbnpTitleSummary {
    <#
    .SYNOPSIS
        A title's issue folders and how many are done, legacy and new, with the first new one.
    #>
    param([Parameter(Mandatory)][string] $TitleFolder)
    $issues = Get-NbnpIssueFolders -TitleFolder $TitleFolder
    $first = @($issues | Where-Object State -EQ 'new' | Select-Object -First 1)
    [pscustomobject]@{
        Issues   = $issues
        Total    = $issues.Count
        Done     = @($issues | Where-Object State -EQ 'done').Count
        Legacy   = @($issues | Where-Object State -EQ 'legacy').Count
        New      = @($issues | Where-Object State -EQ 'new').Count
        FirstNew = if ($first.Count) { $first[0].Path } else { $null }
    }
}

function Find-NbnpTitleFolder {
    <#
    .SYNOPSIS
        The title folder for any folder of a title: the nearest one holding title.toml, this
        folder included, or else a guess at where its title.toml belongs. Returns Path, Exists
        and Problem, which says why the folder is not part of one title (or is $null).

    .DESCRIPTION
        The guess follows the NBNP layout, where the year, volume and issue folders all start with
        the title's short name (KCR_1902, KCR_1902_17, KCR_1902_17_26) and the title folder does
        not (KingsCountyRecord): it is the first folder up from an issue whose name does not
        start with that short name.

        With no title.toml at or above it, a folder above a title (the folder of all titles, a
        desktop, a whole drive) is refused rather than guessed from: one holding a title.toml
        below it, one whose first issue belongs to a title folder below it, and one with no issue
        within MaxDepth levels or MaxFolders folders. The search stops at the first issue found.
    #>
    param(
        [Parameter(Mandatory)][string] $Folder,
        [int] $MaxDepth = 6,
        [int] $MaxFolders = 2000
    )
    $full = [IO.Path]::GetFullPath($Folder)
    # A drive's root keeps its slash: C: alone means the current folder on drive C.
    if ($full.Length -gt [IO.Path]::GetPathRoot($full).Length) { $full = $full.TrimEnd('\', '/') }
    $dir = New-Object IO.DirectoryInfo($full)
    while ($null -ne $dir) {
        if (Test-Path -LiteralPath (Join-Path $dir.FullName 'title.toml') -PathType Leaf) {
            return [pscustomobject]@{ Path = $dir.FullName; Exists = $true; Problem = $null }
        }
        $dir = $dir.Parent
    }
    $rerun = "Open the menu on a title's folder, or on any folder inside it."
    $search = @{ Visited = 0; Max = $MaxFolders; TitleBelow = $null; Full = $false }
    $sample = if (Get-NbnpFirstPage -Folder $full) { $full }
    else { Find-FirstIssue -Folder $full -Depth $MaxDepth -Search $search }
    $problem = $null
    if ($search.TitleBelow) {
        $problem = "This folder is above a title: $($search.TitleBelow) has a title.toml. $rerun"
    }
    elseif (-not $sample) {
        $problem = if ($search.Full) {
            "No newspaper issue was found in the first $MaxFolders folders below this one, so it " +
            "is probably above a title's folder. $rerun"
        }
        else { "No newspaper issue (a folder of page images) was found below this folder. $rerun" }
    }
    $guess = $full
    if ($sample) {
        $prefix = ((Split-Path -Leaf $sample) -split '_')[0] + '_'
        $dir = (New-Object IO.DirectoryInfo($sample)).Parent
        while ($null -ne $dir -and $null -ne $dir.Parent -and
            $dir.Name.StartsWith($prefix, [StringComparison]::OrdinalIgnoreCase)) {
            $dir = $dir.Parent
        }
        if ($null -ne $dir) { $guess = $dir.FullName }
        if ($guess.Length -gt $full.Length) {
            $below = $guess.Substring($full.Length).TrimStart('\', '/')
            $problem = "This folder is above a title: $below looks like a title's folder. $rerun"
        }
    }
    [pscustomobject]@{ Path = $guess; Exists = $false; Problem = $problem }
}

# The first issue folder below Folder in name order, or $null. Notes a title.toml met on the way
# and stops there, and stops when the folder budget runs out.
function Find-FirstIssue([string] $Folder, [int] $Depth, [hashtable] $Search) {
    if ($Depth -le 0) { return $null }
    $children = @(Get-ChildItem -LiteralPath $Folder -Directory -ErrorAction SilentlyContinue |
            Where-Object { -not $_.Name.StartsWith('.') } | Sort-Object { Get-NbnpNaturalKey $_.Name })
    foreach ($child in $children) {
        if ($Search.TitleBelow -or $Search.Full) { return $null }
        if (++$Search.Visited -gt $Search.Max) { $Search.Full = $true; return $null }
        if (Test-Path -LiteralPath (Join-Path $child.FullName 'title.toml') -PathType Leaf) {
            $Search.TitleBelow = $child.Name
            return $null
        }
        if (Get-NbnpFirstPage -Folder $child.FullName) { return $child.FullName }
        $found = Find-FirstIssue -Folder $child.FullName -Depth ($Depth - 1) -Search $Search
        if ($found) { return $found }
    }
    $null
}

function Find-NbnpNextIssueFolder {
    <#
    .SYNOPSIS
        The issue folder after this one in its title, for "Save and next", skipping folders that
        still have a metadata.php. $null after the last.
    #>
    param(
        [Parameter(Mandatory)][string] $IssueFolder,
        [object[]] $Issues
    )
    $full = [IO.Path]::GetFullPath($IssueFolder).TrimEnd('\', '/')
    if ($null -eq $Issues) {
        $Issues = Get-NbnpIssueFolders -TitleFolder (Find-NbnpTitleFolder -Folder $full).Path
    }
    $passed = $false
    foreach ($issue in $Issues) {
        if ($passed -and $issue.State -ne 'legacy') { return $issue.Path }
        if ($issue.Path -eq $full) { $passed = $true }
    }
    $null
}

function Get-NbnpFirstPage {
    <#
    .SYNOPSIS
        The folder's first page image in name order, ignoring hidden files such as ._ copies left
        by macOS. $null when there is none.
    #>
    param([Parameter(Mandatory)][string] $Folder)
    $pages = @(Get-ChildItem -LiteralPath $Folder -File -ErrorAction SilentlyContinue |
            Where-Object { $_.Extension -eq '.jpg' -and -not $_.Name.StartsWith('.') } |
            Sort-Object { Get-NbnpNaturalKey $_.Name })
    if ($pages.Count -gt 0) { $pages[0].FullName } else { $null }
}

#endregion

#region Checking

function ConvertTo-NbnpDate {
    <#
    .SYNOPSIS
        A date typed in a clear form, rewritten YYYY-MM-DD; anything else returned unchanged.

    .DESCRIPTION
        Accepts 1902-03-28, 1902-3-28, March 28, 1902, Mar. 28 1902 and 28 March 1902. Refuses
        forms like 03/04/1902, where day and month could be either way round, which is how the
        old metadata.php files got dates backwards.
    #>
    param([AllowEmptyString()][string] $Text)
    $clean = ($Text.Trim() -replace '\s+', ' ' -replace '\.(?=\s)', '' -replace ',', '')
    $m = [regex]::Match($clean, '^(\d{4})-(\d{1,2})-(\d{1,2})$')
    if ($m.Success) {
        $candidate = '{0}-{1:D2}-{2:D2}' -f $m.Groups[1].Value, [int]$m.Groups[2].Value, [int]$m.Groups[3].Value
        $date = [datetime]::MinValue
        if ([datetime]::TryParseExact($candidate, 'yyyy-MM-dd', [Globalization.CultureInfo]::InvariantCulture,
                [Globalization.DateTimeStyles]::None, [ref]$date)) { return $candidate }
        return $Text
    }
    $formats = [string[]]@('MMMM d yyyy', 'MMM d yyyy', 'd MMMM yyyy', 'd MMM yyyy')
    $date = [datetime]::MinValue
    if ([datetime]::TryParseExact($clean, $formats, [Globalization.CultureInfo]::InvariantCulture,
            [Globalization.DateTimeStyles]::AllowWhiteSpaces, [ref]$date)) {
        return $date.ToString('yyyy-MM-dd', [Globalization.CultureInfo]::InvariantCulture)
    }
    $Text
}

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
    $error_ = { param($field, $message) $findings.Add([pscustomobject]@{ Field = $field; Severity = 'Error'; Message = $message; Id = '' }) }
    $warn = { param($field, $message, $id = '') $findings.Add([pscustomobject]@{ Field = $field; Severity = 'Warning'; Message = $message; Id = $id }) }

    # Clean: trim single-line values, normalise line ends, drop empty optional values.
    $clean = [ordered]@{}
    foreach ($key in $schema.Keys) {
        if (-not $Values.Contains($key) -or $null -eq $Values[$key]) { continue }
        $value = $Values[$key]
        $text = [string]$value.Text
        # As typed on Windows: an accent and its letter as one character, so text compares and
        # searches alike whether it was typed or pasted.
        try { $text = $text.Normalize([Text.NormalizationForm]::FormC) } catch { }
        if ($key -eq 'errata') {
            $text = ($text -replace "`r`n", "`n").TrimEnd()
        }
        else {
            $text = $text.Trim()
        }
        if ($key -eq 'date' -and $value.Kind -eq 'bare') { $text = ConvertTo-NbnpDate $text }
        if ($key -eq 'missing_pages' -and $text -ne '') {
            $text = (($text -split ',') | ForEach-Object { ($_.Trim() -replace '\s*-\s*', '-') }) -join ', '
        }
        if ($text -eq '' -and $schema.Required -notcontains $key) { continue }
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
                        & $warn $key "Date $text is before $($script:EarliestLikelyYear); check the year." 'early-year'
                    }
                    $years = Get-NbnpPathYears $Folder
                    if ($years.Count -gt 0 -and $years -notcontains $date.Year) {
                        & $warn $key "The folder path names $($years -join ', '), but the date is in $($date.Year)." 'folder-year'
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
        if ($key -ne 'errata' -and $text -match '\p{Cc}') {
            & $error_ $key "$(Get-FieldLabel $key) must be a single line, without control characters."
        }
        if ($key -eq 'errata' -and $text -match '[\p{Cc}-[\n\t]]') {
            & $error_ $key 'Errata contains a control character; retype it.'
        }
        if ($text -match '[\uD800-\uDBFF](?![\uDC00-\uDFFF])|(?<![\uD800-\uDBFF])[\uDC00-\uDFFF]') {
            & $error_ $key "$(Get-FieldLabel $key) contains a broken character; retype it."
        }
        $invisible = @([regex]::Matches($text, '\p{Cf}') | ForEach-Object { 'U+{0:X4}' -f [int][char]$_.Value } |
                Select-Object -Unique)
        if ($invisible.Count -gt 0) {
            & $warn $key "$(Get-FieldLabel $key) contains invisible characters ($($invisible -join ', ')), often pasted with text; remove them unless they are meant."
        }
        $length = Get-CharacterCount $text
        if ($script:MaxLength.ContainsKey($key) -and $length -gt $script:MaxLength[$key]) {
            & $error_ $key "$(Get-FieldLabel $key) is $length characters; the site stores at most $($script:MaxLength[$key])."
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

    if ($Kind -eq 'Title' -and -not $clean.Contains('title_id')) {
        & $warn 'title_id' 'Title ID is not set. The file can be saved, but the issues under this title cannot be imported until it is filled in.'
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

function Get-CharacterCount([string] $Text) {
    # Characters as the site and nbnp-ingest count them: code points, so a character outside the
    # Basic Multilingual Plane (two UTF-16 units) counts once.
    $Text.Length - [regex]::Matches($Text, '[\uDC00-\uDFFF]').Count
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
        The four-digit years (1600-2099) a folder path names, such as 1909 in TN_1909_33_05.
    #>
    param([string] $Folder)
    $years = @()
    if (-not $Folder) { return , $years }
    foreach ($m in [regex]::Matches($Folder, '(?<![0-9])(1[6-9][0-9]{2}|20[0-9]{2})(?![0-9])')) {
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

#region Viewing a page

# A view of an image in a box: Scale (box pixels per image pixel) and X, Y (the image point at
# the box's top-left). The form draws the image through it; these functions only do the
# arithmetic, so it can be tested without a screen.

function New-NbnpView {
    <#
    .SYNOPSIS
        A view showing the image at the box's width, from the top: the masthead first.
    #>
    param([double] $ImageWidth, [double] $ImageHeight, [double] $BoxWidth, [double] $BoxHeight)
    $view = [pscustomobject]@{
        ImageWidth = $ImageWidth; ImageHeight = $ImageHeight; BoxWidth = $BoxWidth; BoxHeight = $BoxHeight
        Scale = $BoxWidth / $ImageWidth; X = 0.0; Y = 0.0
    }
    Limit-NbnpView $view
}

function Step-NbnpViewZoom {
    <#
    .SYNOPSIS
        Zooms by Factor, keeping the image point under (AtX, AtY) in the box where it is. The
        scale stays between showing the whole page and 4 box pixels per image pixel.
    #>
    param($View, [double] $Factor, [double] $AtX, [double] $AtY)
    $imageX = $View.X + $AtX / $View.Scale
    $imageY = $View.Y + $AtY / $View.Scale
    $whole = [Math]::Min($View.BoxWidth / $View.ImageWidth, $View.BoxHeight / $View.ImageHeight)
    $View.Scale = [Math]::Max($whole, [Math]::Min(4.0, $View.Scale * $Factor))
    $View.X = $imageX - $AtX / $View.Scale
    $View.Y = $imageY - $AtY / $View.Scale
    Limit-NbnpView $View
}

function Move-NbnpView {
    <#
    .SYNOPSIS
        Pans by a drag of (DeltaX, DeltaY) box pixels: the image follows the pointer.
    #>
    param($View, [double] $DeltaX, [double] $DeltaY)
    $View.X -= $DeltaX / $View.Scale
    $View.Y -= $DeltaY / $View.Scale
    Limit-NbnpView $View
}

function Limit-NbnpView($View) {
    # Keeps the image in the box: no panning past an edge, and an image smaller than the box
    # centred across and kept at the top.
    $visibleWidth = $View.BoxWidth / $View.Scale
    $visibleHeight = $View.BoxHeight / $View.Scale
    $View.X = if ($visibleWidth -ge $View.ImageWidth) { ($View.ImageWidth - $visibleWidth) / 2 }
    else { [Math]::Max(0.0, [Math]::Min($View.X, $View.ImageWidth - $visibleWidth)) }
    $View.Y = if ($visibleHeight -ge $View.ImageHeight) { 0.0 }
    else { [Math]::Max(0.0, [Math]::Min($View.Y, $View.ImageHeight - $visibleHeight)) }
    $View
}

#endregion

#region The last issue saved

# What "Fill from last saved issue" leaves out: these describe one particular copy.
$script:NotCarriedOver = @('missing_pages', 'errata')

function Get-NbnpStateDirectory {
    <#
    .SYNOPSIS
        Where the editor keeps the signed-in user's own state: %LOCALAPPDATA%\nbnp-metadata-editor.
    #>
    $base = if ($env:LOCALAPPDATA) { $env:LOCALAPPDATA } else { Join-Path $HOME '.local/state' }
    Join-Path $base 'nbnp-metadata-editor'
}

# The warnings a user may turn off: those that guess from patterns, not those about the file.
$script:WarningChecks = @(
    @{ Id = 'date-order'; Label = "Date earlier than the previous issue's, or later than the next one's" }
    @{ Id = 'same-date'; Label = 'Same date as another issue of the title' }
    @{ Id = 'same-number'; Label = 'Same volume and issue as another issue of the title' }
    @{ Id = 'folder-number'; Label = 'Volume or issue differs from the folder name' }
    @{ Id = 'lower-number'; Label = "Volume or issue lower than the previous issue's" }
    @{ Id = 'edition-number'; Label = 'Edition is only a number' }
    @{ Id = 'repeat-last'; Label = 'Same volume, issue and date as the last issue you saved' }
    @{ Id = 'folder-year'; Label = 'Year differs from the year in the folder path' }
    @{ Id = 'early-year'; Label = "Date before $($script:EarliestLikelyYear)" }
)

function Get-NbnpWarningChecks {
    <#
    .SYNOPSIS
        The warnings a user may turn off in Settings: Id and Label.
    #>
    foreach ($check in $script:WarningChecks) { [pscustomobject]$check }
}

function Get-NbnpDisabledWarnings {
    <#
    .SYNOPSIS
        The IDs of the warnings this user turned off, from settings.json in the state directory.
        None when the file is missing or unreadable. One by one: wrap the call in @().
    #>
    param([string] $Directory = (Get-NbnpStateDirectory))
    $path = Join-Path $Directory 'settings.json'
    if (-not (Test-Path -LiteralPath $path -PathType Leaf)) { return }
    try {
        $settings = [IO.File]::ReadAllText($path) | ConvertFrom-Json
        $known = @($script:WarningChecks | ForEach-Object { $_.Id })
        $ids = @()
        if ($settings.PSObject.Properties['disabled_warnings']) {
            $ids = @($settings.disabled_warnings | Where-Object { $known -contains $_ })
        }
        $ids
    }
    catch {
        Write-Verbose "Could not read the settings: $($_.Exception.Message)"
    }
}

function Save-NbnpDisabledWarnings {
    <#
    .SYNOPSIS
        Saves the IDs of the warnings this user turned off, to settings.json in the state directory.
    #>
    param(
        [string[]] $Ids = @(),
        [string] $Directory = (Get-NbnpStateDirectory)
    )
    [void](New-Item -ItemType Directory -Force -Path $Directory)
    $text = ConvertTo-Json -InputObject ([ordered]@{ disabled_warnings = @($Ids | Sort-Object -Unique) })
    [IO.File]::WriteAllText((Join-Path $Directory 'settings.json'), $text, $script:Utf8NoBom)
}

function Save-NbnpLastIssue {
    <#
    .SYNOPSIS
        Remembers a saved issue's values, less missing pages and errata, for the next form.

    .DESCRIPTION
        Written to last-issue.toml in the user's state directory, in the issue.toml format, with
        the folder it came from in a comment. Failing to write it never fails the save it follows.
    #>
    param(
        [Parameter(Mandatory)][System.Collections.IDictionary] $Values,
        [Parameter(Mandatory)][string] $Folder,
        [string] $Directory = (Get-NbnpStateDirectory)
    )
    try {
        $kept = [ordered]@{}
        foreach ($key in $Values.Keys) {
            if ($script:NotCarriedOver -notcontains $key) { $kept[$key] = $Values[$key] }
        }
        $text = ConvertTo-NbnpToml -Kind Issue -Values $kept -Header @(
            '# The last issue saved with the NBNP metadata editor, from:', "# $Folder")
        [void](New-Item -ItemType Directory -Force -Path $Directory)
        [IO.File]::WriteAllText((Join-Path $Directory 'last-issue.toml'), $text, $script:Utf8NoBom)
    }
    catch {
        Write-Verbose "Could not remember the last issue: $($_.Exception.Message)"
    }
}

function Get-NbnpLastIssue {
    <#
    .SYNOPSIS
        The last issue this user saved: Folder, Saved (time) and Values. $null when there is none
        or it cannot be read.
    #>
    param([string] $Directory = (Get-NbnpStateDirectory))
    $path = Join-Path $Directory 'last-issue.toml'
    if (-not (Test-Path -LiteralPath $path -PathType Leaf)) { return $null }
    try {
        $parsed = ConvertFrom-NbnpToml -Text ([IO.File]::ReadAllText($path)) -Kind Issue
    }
    catch {
        return $null
    }
    if (-not $parsed.Supported -or $parsed.Values.Count -eq 0) { return $null }
    $folder = if ($parsed.Header.Count -ge 2) { $parsed.Header[1] -replace '^#\s*', '' } else { '' }
    [pscustomobject]@{
        Folder = $folder
        Saved  = (Get-Item -LiteralPath $path).LastWriteTime
        Values = $parsed.Values
    }
}

function Test-NbnpPathUnder {
    <#
    .SYNOPSIS
        True when Path is Folder or inside it, ignoring case as Windows does: whether the last
        issue saved belongs to the title being edited.
    #>
    param([string] $Path, [string] $Folder)
    if (-not $Path -or -not $Folder) { return $false }
    $p = [IO.Path]::GetFullPath($Path).TrimEnd('\', '/')
    $f = [IO.Path]::GetFullPath($Folder).TrimEnd('\', '/')
    if ($p.Equals($f, [StringComparison]::OrdinalIgnoreCase)) { return $true }
    foreach ($separator in @('\', '/')) {
        if ($p.StartsWith($f + $separator, [StringComparison]::OrdinalIgnoreCase)) { return $true }
    }
    $false
}

function Test-NbnpRepeatOfLast {
    <#
    .SYNOPSIS
        A warning when an issue has the same volume, issue and date as the last one saved from
        another folder: usually a filled-in form saved without changing the number.
    #>
    param(
        [Parameter(Mandatory)][System.Collections.IDictionary] $Values,
        [Parameter(Mandatory)][string] $Folder,
        $Last
    )
    if ($null -eq $Last -or -not $Last.Folder) { return }
    $lastFolder = [IO.Path]::GetFullPath($Last.Folder).TrimEnd('\', '/')
    if ($lastFolder -eq [IO.Path]::GetFullPath($Folder).TrimEnd('\', '/')) { return }
    foreach ($key in @('volume', 'issue', 'date', 'edition', 'supplement_title')) {
        $now = if ($Values.Contains($key)) { $Values[$key].Text } else { '' }
        $then = if ($Last.Values.Contains($key)) { $Last.Values[$key].Text } else { '' }
        if ($now -ne $then) { return }
    }
    [pscustomobject]@{
        Field    = 'issue'
        Severity = 'Warning'
        Id       = 'repeat-last'
        Message  = "Volume, issue and date are the same as $(Split-Path -Leaf $Last.Folder), the last issue you saved. Change the issue number and date if this is the next issue."
    }
}

function Get-NbnpSavedIssues {
    <#
    .SYNOPSIS
        The values of every issue in a title saved as issue.toml, by folder path: what
        Test-NbnpIssueSequence compares with. Issues still in metadata.php are left out.
    #>
    param([object[]] $Issues)
    $saved = @{}
    foreach ($issue in @($Issues | Where-Object State -EQ 'done')) {
        try {
            $file = Read-NbnpMetadataFile -Folder $issue.Path -Kind Issue
            if ($file.Supported) { $saved[$issue.Path] = $file.Values }
        }
        catch { Write-Verbose "skipped $($issue.Path): $($_.Exception.Message)" }
    }
    $saved
}

function Get-ValueText($Values, [string] $Key) {
    if ($Values.Contains($Key) -and $null -ne $Values[$Key]) { [string]$Values[$Key].Text } else { '' }
}

function Test-NbnpIssueSequence {
    <#
    .SYNOPSIS
        Warnings from comparing an issue with the title's other saved issues: a date out of
        folder order, a date or number another issue already has, a number unlike the folder
        name's or lower than the issue before, and an edition that is only a number.

    .DESCRIPTION
        Issues is the title's issue folders in order; Saved maps a folder to its saved values
        (Get-NbnpSavedIssues). The findings come one by one: wrap the call in @(). Supplements
        are not compared with other issues. When Errata is
        filled in, the numbering problem is taken as noted there: the number checks are skipped.
    #>
    param(
        [Parameter(Mandatory)][System.Collections.IDictionary] $Values,
        [Parameter(Mandatory)][string] $Folder,
        [object[]] $Issues = @(),
        [hashtable] $Saved = @{}
    )
    $findings = New-Object System.Collections.Generic.List[object]
    $warn = { param($id, $field, $message) $findings.Add([pscustomobject]@{ Field = $field; Severity = 'Warning'; Message = $message; Id = $id }) }
    $full = [IO.Path]::GetFullPath($Folder).TrimEnd('\', '/')
    $date = Get-ValueText $Values 'date'
    $volume = Get-ValueText $Values 'volume'
    $number = Get-ValueText $Values 'issue'
    $edition = Get-ValueText $Values 'edition'
    $checkNumbers = -not (Get-ValueText $Values 'errata').Trim()
    $isSupplement = [bool](Get-ValueText $Values 'supplement_title')

    if ($edition -match '^\d+$') { & $warn 'edition-number' 'edition' "Edition is only a number ($edition). The issue number goes in Issue." }

    $leaf = Split-Path -Leaf $full
    if ($checkNumbers -and $leaf -match '^[^_]+_\d{4}_(\d+)_(\d+)(_|$)') {
        foreach ($pair in @(@('volume', $volume, $Matches[1]), @('issue', $number, $Matches[2]))) {
            if ($pair[1] -match '^\d+$' -and [long]$pair[1] -ne [long]$pair[2]) {
                & $warn 'folder-number' $pair[0] "$(Get-FieldLabel $pair[0]) $($pair[1]) differs from the folder name ($([long]$pair[2])). If printed so, note it in Errata."
            }
        }
    }
    if ($isSupplement) { return $findings.ToArray() }

    # The title's other saved issues that are not supplements, in folder order.
    $others = @(foreach ($issue in $Issues) {
            if ($issue.Path -eq $full -or -not $Saved.ContainsKey($issue.Path)) { continue }
            $v = $Saved[$issue.Path]
            if (Get-ValueText $v 'supplement_title') { continue }
            [pscustomobject]@{ Path = $issue.Path; Name = Split-Path -Leaf $issue.Path; Values = $v }
        })
    $paths = @($Issues | ForEach-Object Path)
    $at = [array]::IndexOf($paths, $full)
    $before = $null; $after = $null
    if ($at -ge 0) {
        foreach ($other in $others) {
            $i = [array]::IndexOf($paths, $other.Path)
            if ($i -lt $at) { $before = $other } elseif ($null -eq $after) { $after = $other }
        }
    }

    if ($date) {
        if ($before -and [string]::CompareOrdinal((Get-ValueText $before.Values 'date'), $date) -gt 0) {
            & $warn 'date-order' 'date' "Date is before the previous issue's, $($before.Name) ($(Get-ValueText $before.Values 'date'))."
        }
        if ($after -and (Get-ValueText $after.Values 'date') -and [string]::CompareOrdinal((Get-ValueText $after.Values 'date'), $date) -lt 0) {
            & $warn 'date-order' 'date' "Date is after the next issue's, $($after.Name) ($(Get-ValueText $after.Values 'date'))."
        }
        $same = @($others | Where-Object { (Get-ValueText $_.Values 'date') -eq $date -and (Get-ValueText $_.Values 'edition') -eq $edition })
        if ($same.Count) { & $warn 'same-date' 'date' "Same date as $($same[0].Name)." }
    }
    if (-not $checkNumbers) { return $findings.ToArray() }

    if ($volume -and $number) {
        $same = @($others | Where-Object {
                (Get-ValueText $_.Values 'volume') -eq $volume -and (Get-ValueText $_.Values 'issue') -eq $number -and
                (Get-ValueText $_.Values 'edition') -eq $edition })
        if ($same.Count) { & $warn 'same-number' 'issue' "Same volume and issue as $($same[0].Name)." }
    }
    # Numbers going backwards, where both are plain numbers.
    $lower = {
        param($first, $second, $label)
        $v1 = Get-ValueText $first 'volume'; $v2 = Get-ValueText $second 'volume'
        if ($v1 -match '^\d+$' -and $v2 -match '^\d+$') {
            if ([long]$v2 -lt [long]$v1) { return 'volume' }
            if ([long]$v2 -gt [long]$v1) { return $null }
        }
        elseif ($v1 -ne $v2) { return $null }
        $n1 = Get-ValueText $first 'issue'; $n2 = Get-ValueText $second 'issue'
        if ($n1 -match '^\d+$' -and $n2 -match '^\d+$' -and [long]$n2 -lt [long]$n1) { return 'issue' }
        $null
    }
    if ($before) {
        $field = & $lower $before.Values $Values
        if ($field) { & $warn 'lower-number' $field "$(Get-FieldLabel $field) is lower than the previous issue's, $($before.Name) ($(Get-ValueText $before.Values $field))." }
    }
    if ($after) {
        $field = & $lower $Values $after.Values
        if ($field) { & $warn 'lower-number' $field "$(Get-FieldLabel $field) is higher than the next issue's, $($after.Name) ($(Get-ValueText $after.Values $field))." }
    }
    $findings.ToArray()
}

#endregion

Export-ModuleMember -Function New-NbnpValue, Get-NbnpSchema, Get-NbnpLanguages, ConvertFrom-NbnpToml,
    Read-NbnpMetadataFile, Find-NbnpTitleFile, Find-NbnpParentIssue, Test-NbnpMetadata,
    Get-NbnpPathYears, ConvertTo-NbnpToml, Save-NbnpMetadataFile, Get-NbnpStateDirectory,
    Save-NbnpLastIssue, Get-NbnpLastIssue, Get-NbnpNaturalKey, Find-NbnpNextIssueFolder,
    Get-NbnpFirstPage, ConvertTo-NbnpDate, Test-NbnpRepeatOfLast, Get-NbnpIssueFolders,
    Get-NbnpTitleSummary, Find-NbnpTitleFolder, New-NbnpView, Step-NbnpViewZoom, Move-NbnpView,
    Get-NbnpSavedIssues, Test-NbnpIssueSequence, Test-NbnpPathUnder, Get-NbnpWarningChecks,
    Get-NbnpDisabledWarnings, Save-NbnpDisabledWarnings
