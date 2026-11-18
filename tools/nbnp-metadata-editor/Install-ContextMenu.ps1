#Requires -Version 5.1
<#
.SYNOPSIS
    Adds "Edit newspaper metadata..." to the Explorer right-click menu of folders, for the
    current user only. The two items of earlier versions, one for titles and one for issues,
    are removed.

.DESCRIPTION
    Writes keys under HKEY_CURRENT_USER\Software\Classes, so no administrator rights are needed.
    The menu runs the editor from where this script is now: after moving or deleting this
    folder, run the installer again (or Uninstall-ContextMenu.ps1). Running it again is safe.

    Each menu item starts Windows PowerShell with -ExecutionPolicy Bypass for that one process,
    so the unsigned scripts run without changing the computer's execution policy.
#>
[CmdletBinding()]
param()

Set-StrictMode -Version 2.0
$ErrorActionPreference = 'Stop'

$editor = Join-Path $PSScriptRoot 'Edit-NbnpMetadata.ps1'
foreach ($required in @($editor, (Join-Path $PSScriptRoot 'NbnpMetadata.psm1'))) {
    if (-not (Test-Path -LiteralPath $required -PathType Leaf)) {
        throw "$required is missing; extract the whole folder before installing."
    }
}
$powershell = Join-Path $env:SystemRoot 'System32\WindowsPowerShell\v1.0\powershell.exe'
if (-not (Test-Path -LiteralPath $powershell -PathType Leaf)) {
    throw "Windows PowerShell was not found at $powershell."
}

# Files from a ZIP downloaded from the web are marked as such; clear that for these scripts.
Get-ChildItem -LiteralPath $PSScriptRoot -Recurse -Include *.ps1, *.psm1 | Unblock-File

# Windows PowerShell reads scripts without a byte order mark as ANSI, so they stay ASCII and
# the menu's ellipsis is built from its code point.
$ellipsis = [string][char]0x2026
# Directory: right-clicking a folder. Directory\Background: right-clicking inside an open one.
$parents = @('Software\Classes\Directory\shell', 'Software\Classes\Directory\Background\shell')

foreach ($parent in $parents) {
    # The separate title and issue items of earlier versions.
    foreach ($old in @('NbnpEditTitle', 'NbnpEditIssue')) {
        $key = [Microsoft.Win32.Registry]::CurrentUser.OpenSubKey("$parent\$old")
        if ($null -eq $key) { continue }
        $ours = $null -ne $key.GetValue('NbnpMetadataEditor')
        $key.Close()
        if ($ours) { [Microsoft.Win32.Registry]::CurrentUser.DeleteSubKeyTree("$parent\$old") }
    }

    # %V is the folder; it is passed as a quoted argument, never run as code.
    $command = '"{0}" -NoProfile -ExecutionPolicy Bypass -STA -WindowStyle Hidden -File "{1}" -Path "%V"' -f
        $powershell, $editor
    $key = [Microsoft.Win32.Registry]::CurrentUser.CreateSubKey("$parent\NbnpEdit")
    try {
        $key.SetValue('', "Edit newspaper metadata$ellipsis")
        $key.SetValue('Icon', "$powershell,0")
        $key.SetValue('NbnpMetadataEditor', $PSScriptRoot)
        $commandKey = $key.CreateSubKey('command')
        try { $commandKey.SetValue('', $command) } finally { $commandKey.Close() }
    }
    finally { $key.Close() }
}

Write-Host "Installed for $env:USERNAME. Right-click any folder of a title and choose Edit newspaper metadata..."
Write-Host 'On Windows 11 the item is under "Show more options" (or Shift+right-click).'
Write-Host "The menu runs the editor from $PSScriptRoot; reinstall if you move this folder."
