#!/usr/bin/env bash
set -Eeuo pipefail
cd "$(dirname "$0")/.."
if [[ ! -f .env ]]; then
  echo "Missing .env. Copy .env.klickspark.example to .env and set unique local credentials." >&2
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
if [[ "${WP_ADMIN_EMAIL}" == *example.invalid ]]; then
  echo "Set a valid local admin email in .env" >&2
  exit 1
fi
compose=(docker compose --env-file .env -f docker-compose.klickspark.yml)
node generator/src/cli.mjs build fabulous-cleaning --force
"${compose[@]}" up -d db wordpress mailpit
for i in $(seq 1 40); do
  if "${compose[@]}" run --rm -T wpcli core version >/dev/null 2>&1; then
    break
  fi
  if [[ "$i" -eq 40 ]]; then echo "WordPress did not become ready." >&2; exit 1; fi
  sleep 3
done
if ! "${compose[@]}" run --rm -T wpcli core is-installed >/dev/null 2>&1; then
  "${compose[@]}" run --rm -T -e WP_ADMIN_USER -e WP_ADMIN_EMAIL -e WP_ADMIN_PASSWORD wpcli     core install --url="http://localhost:${KS_PORT:-8080}" --title="Fabulous Cleaning (Local)"     --admin_user="$WP_ADMIN_USER" --admin_email="$WP_ADMIN_EMAIL"     --admin_password="$WP_ADMIN_PASSWORD" --skip-email
fi
"${compose[@]}" run --rm -T wpcli theme activate fabulous-cleaning
"${compose[@]}" run --rm -T wpcli eval-file /opt/klickspark/seed.php
echo "Local WordPress initialized. Booking and payments are NOT configured."
