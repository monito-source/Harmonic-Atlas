#!/usr/bin/env sh
set -eu

SERVICE_URL="${SERVICE_URL:-http://localhost:8080}"
SERVICE_URL="${SERVICE_URL%/}"

curl -fsS "$SERVICE_URL/health" | python3 -m json.tool
