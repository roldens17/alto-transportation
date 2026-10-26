# KlickSpark WordPress Local Development

Initial **unverified** Docker Compose environment for Fabulous Cleaning. Not yet a production-ready booking/payment website.

## Prerequisites

Docker Engine with Compose, Node.js 22, Python 3 with PyYAML 6.0.3, ports 8080 and 8025.

## Setup

1. Copy `.env.klickspark.example` to `.env`; replace placeholder passwords with unique local secrets.
2. Run `node generator/src/cli.mjs build fabulous-cleaning --force`.
3. Run `docker compose --env-file .env -f docker-compose.klickspark.yml up -d`.
4. Visit `http://localhost:8080` and complete the WordPress setup wizard.
5. Run `docker compose --env-file .env -f docker-compose.klickspark.yml run --rm wpcli theme list` to check theme visibility.
6. Install licensed ACF Pro and WooCommerce packages before enabling booking; activate the child theme after setup.

Mailpit UI: `http://localhost:8025`. WordPress outbound email is **not** automatically routed to Mailpit.

## Limitations

Parent theme remains at `wordpress/klickspark-base`; plugin extraction is pending. No WP-CLI service/crew seeding, booking/payments provisioning, gateway setup, or checkout integration tests yet. Named volumes store local data. Do not use `docker compose down -v` without backups. Do not expose this development stack to the public internet.

Next: idempotent WP-CLI setup, WooCommerce test gateway, Mailpit routing, and end-to-end tests.
