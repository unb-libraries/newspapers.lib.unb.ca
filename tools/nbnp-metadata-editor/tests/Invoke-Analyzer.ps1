# Checks the scripts with PSScriptAnalyzer for Windows PowerShell 5.1 compatibility.
# Windows Forms and System.Drawing types are reported as missing because the editor loads them
# itself with Add-Type, so those findings are left out. Exits 1 on any other finding.
$ErrorActionPreference = 'Stop'
$profile51 = 'win-8_x64_10.0.17763.0_5.1.17763.316_x64_4.0.30319.42000_framework'
$settings = @{
    IncludeRules = @('PSUseCompatibleSyntax', 'PSUseCompatibleCommands', 'PSUseCompatibleTypes',
        'PSAvoidUsingCmdletAliases', 'PSUseDeclaredVarsMoreThanAssignments',
        'PSAvoidAssignmentToAutomaticVariable', 'PSPossibleIncorrectComparisonWithNull')
    Rules        = @{
        PSUseCompatibleSyntax   = @{ Enable = $true; TargetVersions = @('5.1') }
        PSUseCompatibleCommands = @{ Enable = $true; TargetProfiles = @($profile51) }
        PSUseCompatibleTypes    = @{ Enable = $true; TargetProfiles = @($profile51) }
    }
}
$findings = Get-ChildItem (Join-Path $PSScriptRoot '..') -File |
    Where-Object Extension -In '.ps1', '.psm1' |
    ForEach-Object { Invoke-ScriptAnalyzer -Path $_.FullName -Settings $settings } |
    Where-Object { $_.Message -notmatch "'(System\.)?(Windows\.Forms|Drawing)\." }
if ($findings) {
    $findings | Format-Table -AutoSize -Wrap RuleName, ScriptName, Line, Message | Out-String -Width 200
    exit 1
}
'PSScriptAnalyzer: no Windows PowerShell 5.1 compatibility findings.'
