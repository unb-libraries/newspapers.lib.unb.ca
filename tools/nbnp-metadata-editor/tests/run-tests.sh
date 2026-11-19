#!/usr/bin/env bash
# Checks the scripts for Windows PowerShell 5.1 compatibility and runs the Pester tests, in a
# PowerShell container, then checks every valid issue fixture, as the editor saves it, with
# nbnp-ingest check built from this repository.
#
# Needs docker and cargo. Windows PowerShell 5.1 and the form itself are not covered: see the
# smoke checklist in ../README.md.
set -euo pipefail

here=$(cd "$(dirname "$0")" && pwd)
editor=$(dirname "$here")
ingest=$(cd "$editor/../nbnp-ingest" && pwd)
image=mcr.microsoft.com/powershell:lts-7.4-ubuntu-22.04

out=$(mktemp -d)
trap 'rm -rf "$out"' EXIT

docker run --rm --user "$(id -u):$(id -g)" -e HOME=/tmp -e NBNP_EXPORT=/out \
    -v "$editor":/src:ro -v "$out":/out "$image" pwsh -NoProfile -Command '
        $ErrorActionPreference = "Stop"
        Set-PSRepository PSGallery -InstallationPolicy Trusted
        Install-Module Pester -MinimumVersion 5.5 -Scope CurrentUser -Force | Out-Null
        Install-Module PSScriptAnalyzer -Scope CurrentUser -Force | Out-Null
        & /src/tests/Invoke-Analyzer.ps1
        if ($LASTEXITCODE) { exit $LASTEXITCODE }
        $config = New-PesterConfiguration
        $config.Run.Path = "/src/tests"
        # The UI tests drive the real window, so they need Windows: see tests/ui.
        $config.Run.ExcludePath = @("/src/tests/ui/*")
        $config.Run.Exit = $true
        Invoke-Pester -Configuration $config'

# Each exported issue gets one page image so nbnp-ingest sees an issue directory.
cp "$here/fixtures/title.toml" "$out/News/"
for dir in "$out"/News/*/; do
    printf 'x' > "$dir/001_TN_1909_33_05_01.jpg"
done

cargo build --quiet --manifest-path "$ingest/Cargo.toml"
report=$("$ingest/target/debug/nbnp-ingest" check --config "$ingest/config/hammer.toml" "$out" 2>/dev/null || true)
echo "$report" | tail -n 4
if ! grep -q '^  0 with errors' <<<"$report"; then
    echo "nbnp-ingest check refused a file the editor saves:" >&2
    echo "$report" >&2
    exit 1
fi
echo "nbnp-ingest check accepts every valid fixture as the editor saves it."
