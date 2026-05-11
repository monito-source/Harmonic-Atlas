#!/usr/bin/env bash
set -euo pipefail

PLUGIN_DIR="wp-song-study-blocks"
ZIP_NAME="${PLUGIN_DIR}.zip"
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
OUTPUT_ZIP="${ROOT_DIR}/${PLUGIN_DIR}/${ZIP_NAME}"
TMP_ZIP="${OUTPUT_ZIP}.tmp"

cd "${ROOT_DIR}"

rm -f "${TMP_ZIP}"

zip -r "${TMP_ZIP}" "${PLUGIN_DIR}" \
  -x "${PLUGIN_DIR}/assets/admin-app/*" \
  -x "${PLUGIN_DIR}/services/*" \
  -x "${PLUGIN_DIR}/src/*" \
  -x "${PLUGIN_DIR}/docs/*" \
  -x "*/node_modules/*" \
  -x "*/.git/*" \
  -x "*/.DS_Store" \
  -x "*/__MACOSX/*" \
  -x "*/._*" \
  -x "*.zip"

mv "${TMP_ZIP}" "${OUTPUT_ZIP}"
unzip -t "${OUTPUT_ZIP}" >/dev/null

echo "Created ${OUTPUT_ZIP}"
