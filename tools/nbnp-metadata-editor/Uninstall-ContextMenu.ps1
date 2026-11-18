#Requires -Version 5.1
<#
.SYNOPSIS
    Removes the newspaper metadata item, and those of earlier versions, from the current user's
    Explorer right-click menu.

.DESCRIPTION
    Deletes only the keys Install-ContextMenu.ps1 created (those carrying its
    NbnpMetadataEditor marker). Metadata files and this folder are left as they are.
#>
[CmdletBinding()]
param()

Set-StrictMode -Version 2.0
$ErrorActionPreference = 'Stop'

$removed = 0
foreach ($parent in @('Software\Classes\Directory\shell', 'Software\Classes\Directory\Background\shell')) {
    foreach ($name in @('NbnpEdit', 'NbnpEditTitle', 'NbnpEditIssue')) {
        $path = "$parent\$name"
        $key = [Microsoft.Win32.Registry]::CurrentUser.OpenSubKey($path)
        if ($null -eq $key) { continue }
        $ours = $null -ne $key.GetValue('NbnpMetadataEditor')
        $key.Close()
        if (-not $ours) {
            Write-Warning "Left HKCU\$path in place: it was not created by Install-ContextMenu.ps1."
            continue
        }
        [Microsoft.Win32.Registry]::CurrentUser.DeleteSubKeyTree($path)
        $removed++
    }
}
Write-Host "Removed $removed menu entries. Metadata files were not touched."
