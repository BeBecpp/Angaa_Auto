#!/usr/bin/env bash
set -euo pipefail
script_dir="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
bundle_dir="$(dirname -- "$script_dir")"
project_dir="${1:-.}"
if [[ ! -f "$project_dir/artisan" || ! -f "$project_dir/composer.json" ]]; then
  echo 'Expected an existing Laravel project (artisan + composer.json). Usage: bash handoff/scripts/install-brand-assets.sh app' >&2
  exit 1
fi
project_dir="$(CDPATH= cd -- "$project_dir" && pwd)"
destination="$project_dir/public/brand"
assets=(
  "$bundle_dir/assets/brand/angaa-auto-logo-primary.png"
  "$bundle_dir/assets/brand/angaa-auto-logo-reversed-navy.png"
  "$bundle_dir/assets/brand/angaa-auto-mark.png"
  "$bundle_dir/assets/mascot/angaa-auto-mascot-welcome.png"
  "$bundle_dir/assets/mascot/angaa-auto-mascot-success.png"
)
# Preflight all sources and destinations before copying anything.
for source in "${assets[@]}"; do
  [[ -f "$source" ]] || { echo "Missing source: $source" >&2; exit 1; }
  target="$destination/$(basename -- "$source")"
  [[ ! -L "$target" ]] || { echo "Refusing symlink destination: $target" >&2; exit 1; }
  if [[ -e "$target" ]] && ! cmp -s -- "$source" "$target"; then
    echo "Refusing to overwrite a different file: $target" >&2
    exit 1
  fi
done
mkdir -p -- "$destination"
for source in "${assets[@]}"; do
  target="$destination/$(basename -- "$source")"
  if [[ ! -e "$target" ]]; then cp -- "$source" "$target"; fi
done
echo "Installed/verified 5 selected ANGAA AUTO PNG assets in $destination"
echo 'The brand board stays in the handoff. No application dependencies, migrations or providers were installed.'
