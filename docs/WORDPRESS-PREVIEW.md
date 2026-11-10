# Disposable local WordPress preview (per client)

This is an explicitly invoked **local-only** Docker WordPress preview, not a hosted preview or deployment. Requires Docker Compose, Node 22, Python 3/PyYAML and local non-placeholder `.env` credentials from `.env.klickspark.example`.

```bash
bash scripts/klickspark-preview.sh start fabulous-cleaning
# Browser on Docker host: http://localhost:8091
bash scripts/klickspark-preview.sh stop fabulous-cleaning
```

For a wizard-generated client, first import the YAML via `node generator/src/cli.mjs import new-cleaning --file /path/to/site.yaml`, then run `bash scripts/klickspark-preview.sh start new-cleaning`. The script validates the client config, builds its child theme and activates that theme. The first iteration supports client configurations for supported industries (currently cleaning). Fabulous Cleaning receives its existing example draft page/service seed. Other clients receive generic draft-only Home, Services, About, Contact and Privacy pages with placeholder content. Existing pages are preserved. This does **not** automatically publish a finished website.

The preview explicitly refuses a client YAML file that enables quotes or payments. Booking remains subject to separate runtime review; this local preview does not authorize production activation. **Do not use real customer data.**

Each client uses a distinct Compose project `klickspark-preview-<client>` and distinct WordPress/database/private storage volumes. The site and Mailpit are loopback-only on ports 8091 and 8092 by default. To run multiple previews concurrently, set different `KS_PREVIEW_PORT` and `KS_PREVIEW_MAILPIT_PORT` values for each command. `stop` invokes `down -v`, **permanently deleting that preview client's isolated Docker data**.

**Filesystem caveat:** all previews still use the shared parent-theme source and build into `generated/<client>` with `--force`. Do not edit generated artifacts manually or run a build for the same client concurrently. The WordPress volumes are isolated, not the source files. The Docker Compose configuration mounts both seed scripts; only the matching seed script executes for the selected client. The generic seeder verifies the active child theme matches the selected client and never publishes pages.

Inspect responsive pages, accessible controls, browser console, WordPress logs, draft vs published status and privacy before approving a design. On 2026-11-10, this script successfully started the merged PR #20 source on Docker Desktop; the Fabulous Cleaning homepage was verified in the browser at `http://localhost:8091`. Draft seed content, disabled quotes/payments and isolated local volumes were preserved. This confirms the local preview startup and homepage, not licensed ACF integration or production booking readiness. Nothing here publishes or modifies a live client site.


## Optional working booking demo

For the Fabulous Cleaning preview only, explicitly opt in to labeled sample services and a demo crew schedule:

```bash
KS_PREVIEW_BOOKING_DEMO=1 bash scripts/klickspark-preview.sh start fabulous-cleaning
```

The homepage has a Booking link and Request a Cleaning button. The demo publishes `/booking/` and three separately labeled sample services; existing business services remain drafts. It uses native WordPress metadata, real availability/hold validation, and the private administrator review queue, with no ACF or WooCommerce runtime dependency. Native records carry `_ks_booking_storage = native` so installing ACF later does not reinterpret their array fields. ACF-authored records continue through ACF when installed. Native field authoring UI is not included.

The demo seed requires WP-CLI, the explicit flag, a loopback site URL, the Fabulous Cleaning child theme, slot bookings, no payment and quotes off. It refuses to replace an edited/published business booking page and preserves tagged demo records on repeated runs. Use test details: these sample times do not arrange a real cleaning. This option does not enable quotes or payments.

After a generated theme rebuild, the startup script recreates only the WordPress container to refresh the directory bind mount. Database and site volumes are preserved; this avoids an empty/stale child theme after `--force`.
