# Disposable local WordPress preview (per client)

This is an explicitly invoked **local-only** Docker WordPress preview, not a hosted preview or deployment. Requires Docker Compose, Node 22, Python 3/PyYAML and local non-placeholder `.env` credentials from `.env.klickspark.example`.

```bash
bash scripts/klickspark-preview.sh start fabulous-cleaning
# Browser on Docker host: http://localhost:8091
bash scripts/klickspark-preview.sh stop fabulous-cleaning
```

For a wizard-generated client, first import the YAML via `node generator/src/cli.mjs import new-cleaning --file /path/to/site.yaml`, then run `bash scripts/klickspark-preview.sh start new-cleaning`. The script validates the client config, builds its child theme and activates that theme. The first iteration supports client configurations for supported industries (currently cleaning). Only Fabulous Cleaning receives the existing example draft page/service seed; other clients get a fresh WordPress installation with their generated theme and no seeded pages. This does **not** automatically publish a finished website.

The preview explicitly refuses a client YAML file that enables quotes or payments. Booking remains subject to separate runtime review; this local preview does not authorize production activation. **Do not use real customer data.**

Each client uses a distinct Compose project `klickspark-preview-<client>` and distinct WordPress/database/private storage volumes. The site and Mailpit are loopback-only on ports 8091 and 8092 by default. To run multiple previews concurrently, set different `KS_PREVIEW_PORT` and `KS_PREVIEW_MAILPIT_PORT` values for each command. `stop` invokes `down -v`, **permanently deleting that preview client's isolated Docker data**.

**Filesystem caveat:** all previews still use the shared parent-theme source and build into `generated/<client>` with `--force`. Do not edit generated artifacts manually or run a build for the same client concurrently. The WordPress volumes are isolated, not the source files. The Docker Compose configuration still mounts the Fabulous Cleaning seed file for the WP-CLI container, but it is **executed only** for the Fabulous Cleaning client.

Inspect responsive pages, accessible controls, browser console, WordPress logs, draft vs published status and privacy before approving a design. This script is not yet hands-on Docker/browser verified. Nothing here deploys, merges, or modifies a live site.
