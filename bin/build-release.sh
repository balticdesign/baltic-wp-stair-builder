#!/usr/bin/env bash
# Build the distributable release zip from HEAD.
#
# The zip is what ships to client sites, uploaded through wp-admin. It is cut
# with `git archive`, so .gitattributes export-ignore rules decide what ships:
# no .md files, no docs/, no bin/, no composer.*, no dot files. Never ship a
# GitHub "Download ZIP" — that packs the whole tree under a `-main` suffix.
set -euo pipefail

cd "$(git rev-parse --show-toplevel)"

SLUG="baltic-wp-stair-builder"
MAIN_FILE="baltic-wp-stairbuilder.php"

if [[ -n "$(git status --porcelain)" ]]; then
    echo "ERROR: working tree is dirty. Commit or stash before building." >&2
    exit 1
fi

header_version=$(grep -m1 -oP '^Version:\s*\K[0-9.]+' "$MAIN_FILE" || true)
constant_version=$(grep -m1 -oP "BALTIC_STAIRBUILDER_VERSION',\s*'\K[0-9.]+" "$MAIN_FILE" || true)
stable_tag=$(grep -m1 -oP '^Stable tag:\s*\K[0-9.]+' readme.txt || true)

if [[ -z "$header_version" || -z "$constant_version" || -z "$stable_tag" ]]; then
    echo "ERROR: could not read all three version strings:" >&2
    echo "  header='$header_version' constant='$constant_version' stable tag='$stable_tag'" >&2
    exit 1
fi

if [[ "$header_version" != "$constant_version" || "$header_version" != "$stable_tag" ]]; then
    echo "ERROR: version mismatch:" >&2
    echo "  plugin header:                 $header_version" >&2
    echo "  BALTIC_STAIRBUILDER_VERSION:   $constant_version" >&2
    echo "  readme.txt Stable tag:         $stable_tag" >&2
    exit 1
fi

mkdir -p dist
zip_path="dist/${SLUG}-${header_version}.zip"
git archive --format=zip --prefix="${SLUG}/" -o "$zip_path" HEAD

echo "Built $zip_path ($header_version)"
du -h "$zip_path" | cut -f1 | xargs echo "Size:"
