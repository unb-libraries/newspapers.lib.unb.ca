#!/usr/bin/env bash
# Builds nbnp-metadata-editor-<version>.zip, holding a folder nbnp-metadata-editor with what
# a Windows user needs (no tests), and its .sha256, in the directory given (default: here).
# The version is the one in Edit-NbnpMetadata.ps1. CI runs it for a tag
# nbnp-metadata-editor-vX.Y.Z; the tag must match that version.
set -euo pipefail

here=$(cd "$(dirname "$0")" && pwd)
out=$(cd "${1:-.}" && pwd)
version=$(sed -n "s/^\$script:Version = '\([0-9.]*\)'.*/\1/p" "$here/Edit-NbnpMetadata.ps1")
[ -n "$version" ] || { echo "no \$script:Version in Edit-NbnpMetadata.ps1" >&2; exit 1; }
if [ -n "${TAG:-}" ] && [ "$TAG" != "nbnp-metadata-editor-v$version" ]; then
    echo "tag $TAG does not match Edit-NbnpMetadata.ps1 version $version" >&2
    exit 1
fi

stage=$(mktemp -d)
trap 'rm -rf "$stage"' EXIT
dir="$stage/nbnp-metadata-editor"
mkdir "$dir"
cp "$here"/{Edit-NbnpMetadata.ps1,NbnpMetadata.psm1,Install-ContextMenu.ps1,Uninstall-ContextMenu.ps1,README.md} "$dir/"
# cmd.exe expects CRLF line endings.
for cmd in Install.cmd Uninstall.cmd; do sed 's/\r*$/\r/' "$here/$cmd" > "$dir/$cmd"; done

zip="nbnp-metadata-editor-$version.zip"
rm -f "$out/$zip"
(cd "$stage" && TZ=UTC zip -qrX "$out/$zip" nbnp-metadata-editor)
(cd "$out" && sha256sum "$zip" > "$zip.sha256")
echo "$out/$zip"
