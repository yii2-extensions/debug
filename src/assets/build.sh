#!/bin/sh
# Compiles SCSS sources to the sibling css/ directory.
# Uses the `sass` binary when available (Dart Sass), else falls back to `npx sass`.
set -e
cd "$(dirname "$0")"
if command -v sass >/dev/null 2>&1; then
    sass_cmd="sass"
else
    sass_cmd="npx --yes sass"
fi
for file in main toolbar timeline; do
    $sass_cmd scss/$file.scss css/$file.css --no-source-map --style=compressed
done
