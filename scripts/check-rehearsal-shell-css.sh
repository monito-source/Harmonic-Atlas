#!/usr/bin/env bash
set -euo pipefail

css_file="pertenencia-digital/style.css"
js_file="wp-song-study-blocks/assets/project-frontend/rehearsal-tabs.js"

if [[ ! -f "$css_file" ]]; then
  echo "Missing $css_file" >&2
  exit 1
fi

if [[ ! -f "$js_file" ]]; then
  echo "Missing $js_file" >&2
  exit 1
fi

forbidden_patterns=(
  ".pd-rehearsal-page-shell *"
  ".pd-rehearsal-page-shell *::before"
  ".pd-rehearsal-page-shell *::after"
  ".pd-rehearsal-page-shell :where("
  ".pd-rehearsal-page-shell .pd-rehearsal-"
  ".pd-rehearsal-page-shell .pd-rehearsal-section--availability .pd-rehearsal-grid"
  ".pd-rehearsal-page-content *"
  ".pd-rehearsal-page-content *::before"
  ".pd-rehearsal-page-content *::after"
  ".pd-rehearsal-page-content :where("
  ".pd-rehearsal-page-content .pd-rehearsal-"
  ".pd-rehearsal-block :where("
)

for pattern in "${forbidden_patterns[@]}"; do
  if grep -Fq "$pattern" "$css_file"; then
    echo "Forbidden rehearsal shell selector found: $pattern" >&2
    echo "Keep the rehearsal page container passive; use component-owned selectors only." >&2
    exit 1
  fi
done

if grep -Fq "content-visibility" "$css_file"; then
  echo "Forbidden rehearsal rendering optimization found: content-visibility" >&2
  echo "Do not use content-visibility inside the rehearsal planner; it can prevent tabbed panels from painting reliably." >&2
  exit 1
fi

if grep -Fq "window.stop(" "$js_file"; then
  echo "Forbidden rehearsal runtime stop found: window.stop(" >&2
  echo "The frontend must never stop document loading for regular rehearsal users." >&2
  exit 1
fi

echo "Rehearsal shell guard passed."
