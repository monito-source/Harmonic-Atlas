#!/usr/bin/env sh
set -eu

if [ "$#" -lt 1 ]; then
  echo "Usage: $0 path/to/score-image" >&2
  exit 2
fi

SERVICE_URL="${SERVICE_URL:-http://localhost:8080}"
SERVICE_URL="${SERVICE_URL%/}"
OMR_API_KEY="${OMR_API_KEY:-}"
IMAGE_PATH="$1"
FILE_NAME="$(basename "$IMAGE_PATH")"

if [ ! -r "$IMAGE_PATH" ]; then
  echo "File is not readable: $IMAGE_PATH" >&2
  exit 2
fi

case "$(printf '%s' "$FILE_NAME" | tr '[:upper:]' '[:lower:]')" in
  *.jpg|*.jpeg) MIME_TYPE="image/jpeg" ;;
  *.tif|*.tiff) MIME_TYPE="image/tiff" ;;
  *.bmp) MIME_TYPE="image/bmp" ;;
  *.pdf) MIME_TYPE="application/pdf" ;;
  *) MIME_TYPE="image/png" ;;
esac

REQUEST_FILE="$(mktemp)"
RESPONSE_FILE="$(mktemp)"
trap 'rm -f "$REQUEST_FILE" "$RESPONSE_FILE"' EXIT

python3 - "$IMAGE_PATH" "$FILE_NAME" "$MIME_TYPE" > "$REQUEST_FILE" <<'PY'
import base64
import json
import sys

path, file_name, mime_type = sys.argv[1:4]
with open(path, "rb") as handle:
    encoded = base64.b64encode(handle.read()).decode("ascii")

print(json.dumps({
    "song_id": "smoke-test",
    "attachment_id": "score-image",
    "file_name": file_name,
    "mime_type": mime_type,
    "image_base64": encoded,
    "score": {"tempo": 100, "instrument": "piano", "notes": ""}
}))
PY

HTTP_CODE_FILE="$(mktemp)"
trap 'rm -f "$REQUEST_FILE" "$RESPONSE_FILE" "$HTTP_CODE_FILE"' EXIT

if [ -n "$OMR_API_KEY" ]; then
  curl -sS -X POST "$SERVICE_URL/omr" \
    -H "Content-Type: application/json" \
    -H "Authorization: Bearer $OMR_API_KEY" \
    --data-binary "@$REQUEST_FILE" \
    -w "%{http_code}" \
    -o "$RESPONSE_FILE" > "$HTTP_CODE_FILE"
else
  curl -sS -X POST "$SERVICE_URL/omr" \
    -H "Content-Type: application/json" \
    --data-binary "@$REQUEST_FILE" \
    -w "%{http_code}" \
    -o "$RESPONSE_FILE" > "$HTTP_CODE_FILE"
fi

HTTP_CODE="$(cat "$HTTP_CODE_FILE")"
if [ "$HTTP_CODE" -lt 200 ] || [ "$HTTP_CODE" -ge 300 ]; then
  echo "OMR request failed with HTTP $HTTP_CODE" >&2
  python3 -m json.tool "$RESPONSE_FILE" >&2 || cat "$RESPONSE_FILE" >&2
  exit 1
fi

python3 - "$RESPONSE_FILE" <<'PY'
import json
import sys

with open(sys.argv[1], "r", encoding="utf-8") as handle:
    payload = json.load(handle)

print(json.dumps({
    "ok": payload.get("ok"),
    "engine": payload.get("engine"),
    "song_id": payload.get("song_id"),
    "attachment_id": payload.get("attachment_id"),
    "message": payload.get("message"),
    "warnings": payload.get("warnings", []),
    "has_musicxml": bool(payload.get("musicxml")),
}, indent=2))

if not payload.get("musicxml"):
    raise SystemExit("Response does not include musicxml")
PY
