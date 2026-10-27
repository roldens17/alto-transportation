#!/usr/bin/env bash
# Isolated local Fabulous Cleaning WordPress preview; never production.
set -Eeuo pipefail
cd "$(dirname "$0")/.."
if [[ "${1:-}" != "start" && "${1:-}" != "stop" ]]; then
  echo "Usage: bash scripts/klickspark-preview.sh start|stop" >&2
  exit 2
fi
if [[ ! -f .env ]]; then
  echo "Missing local .env; see .env.klickspark.example." >&2
  exit 1
fi
set -a
# shellcheck disable=SC1091
source .env
set +a
for name in DB_PASSWORD DB_ROOT_PASSWORD WP_ADMIN_USER WP_ADMIN_EMAIL WP_ADMIN_PASSWORD; do
  if [[ -z "${!name:-}" || "${!name}" == replace-* ]]; then
    echo "Set a non-placeholder $name in .env" >&2
    exit 1
  fi
done
if [[ "$WP_ADMIN_EMAIL" == *example.invalid ]]; then
  echo "Use a valid local admin email in .env" >&2
  exit 1
fi
# Separate Compose project means separate database, WordPress files, and private storage.
export KS_PORT="${KS_PREVIEW_PORT:-8091}"
export MAILPIT_PORT="${KS_PREVIEW_MAILPIT_PORT:-8092}"
compose=(docker compose --env-file .env -p klickspark-preview -f docker-compose.klickspark.yml)
if [[ "$1" == stop ]]; then
  "${compose[@]}" down -v
  echo "Disposable preview stopped; its isolated Docker volumes removed."
  exit 0
fi
if [[ "$KS_PORT" == "${KS_DEV_PORT:-8080}" || "$MAILPIT_PORT" == "${KS_DEV_MAILPIT_PORT:-8025}" ]]; then
  echo "Preview ports must differ from normal development ports." >&2
  exit 1
fi
node generator/src/cli.mjs build fabulous-cleaning --force
"${compose[@]}" up -d db wordpress mailpit
ready=0
for i in $(seq 1 40); do
  if "${compose[@]}" run --rm -T wpcli core version >/dev/null 2>&1; then ready=1; break; fi
  sleep 3
done
if [[ "$ready" != 1 ]]; then echo "WordPress preview failed to become ready." >&2; exit 1; fi
if ! "${compose[@]}" run --rm -T wpcli core is-installed >/dev/null 2>&1; then
  "${compose[@]}" run --rm -T wpcli core install \
    --url="http://localhost:$KS_PORT" --title="Fabulous Cleaning (Disposable Preview)" \
    --admin_user="$WP_ADMIN_USER" --admin_email="$WP_ADMIN_EMAIL" \
    --admin_password="$WP_ADMIN_PASSWORD" --skip-email
fi
"${compose[@]}" run --rm -T wpcli theme activate fabulous-cleaning
"${compose[@]}" run --rm -T wpcli eval-file /opt/klickspark/seed.php
echo "Preview: http://localhost:$KS_PORT"
echo "Local-only; not deployed. Stop and delete isolated preview data with: bash scripts/klickspark-preview.sh stop"
