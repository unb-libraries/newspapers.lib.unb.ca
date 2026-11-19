#Requires -Version 5.1
<#
.SYNOPSIS
    Runs the UI tests (Editor.UI.Tests.ps1) in Windows PowerShell 5.1, on a Windows desktop.
    Screenshots go to -Shots (default: a folder in %TEMP%). Exits non-zero when a test fails.
#>
param([string] $Shots = (Join-Path ([IO.Path]::GetTempPath()) 'nbnp-ui-shots'))
$ErrorActionPreference = 'Stop'
if ($PSVersionTable.PSEdition -ne 'Desktop') { throw 'Run this in Windows PowerShell 5.1 (powershell.exe), as the editor runs.' }
if (-not (Get-Module -ListAvailable Pester | Where-Object Version -GE '5.5.0')) {
    [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
    Install-PackageProvider NuGet -MinimumVersion 2.8.5.201 -Scope CurrentUser -Force | Out-Null
    Install-Module Pester -MinimumVersion 5.5 -Scope CurrentUser -Force -SkipPublisherCheck
}
Import-Module Pester -MinimumVersion 5.5

# FlaUI, for UI Automation through Windows' UIA3 interface; each package is checked against its
# SHA-256 before use.
$packages = @(
    @{ Id = 'Interop.UIAutomationClient'; Version = '10.19041.0'; Lib = 'lib/net45'; Sha256 = '0d2ed17db2cb13a262f0580ab992db87577651c25a9899053df4114b4d9ff3b1' }
    @{ Id = 'FlaUI.Core'; Version = '4.0.0'; Lib = 'lib/net48'; Sha256 = '5ce1496eebe099cb0751a61b82694351b694efe5be6eb888c41a25aa7b8827fa' }
    @{ Id = 'FlaUI.UIA3'; Version = '4.0.0'; Lib = 'lib/net48'; Sha256 = '161310f8b018bb5d41efc1aca070aca13eba77f6aff46decf19fcfe52f355905' }
)
$flaui = Join-Path ([IO.Path]::GetTempPath()) 'nbnp-ui-flaui'
[void](New-Item -ItemType Directory -Force -Path $flaui)
foreach ($package in $packages) {
    $zip = Join-Path $flaui "$($package.Id).$($package.Version).zip"
    if (-not (Test-Path $zip)) {
        [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
        Invoke-WebRequest -UseBasicParsing -OutFile $zip "https://www.nuget.org/api/v2/package/$($package.Id)/$($package.Version)"
    }
    $hash = (Get-FileHash -Algorithm SHA256 $zip).Hash
    if ($hash -ne $package.Sha256) { throw "$($package.Id) $($package.Version): SHA-256 $hash, expected $($package.Sha256)" }
    $out = Join-Path $flaui $package.Id
    Expand-Archive -Force -Path $zip -DestinationPath $out
    Copy-Item -Force (Join-Path $out "$($package.Lib)/*.dll") $flaui
}
$env:NBNP_FLAUI = $flaui
$env:NBNP_UI_SHOTS = $Shots
$config = New-PesterConfiguration
$config.Run.Path = Join-Path $PSScriptRoot 'Editor.UI.Tests.ps1'
$config.Run.Exit = $true
$config.Output.Verbosity = 'Detailed'
Invoke-Pester -Configuration $config
