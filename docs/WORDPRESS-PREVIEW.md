# Disposable WordPress preview (Fabulous Cleaning only)

This is a **local-only**, explicitly invoked WordPress browser preview for the existing Fabulous Cleaning showcase. It is not a public URL, hosted preview service, generic client preview, or deployment pipeline. Requires Docker Compose, Node 22, Python 3 + PyYAML, and a local `.env` populated from `.env.klickspark.example` with unique non-placeholder development credentials.

```bash
bash scripts/klickspark-preview.sh start
# Visit http://localhost:8091 (on the computer running Docker)
bash scripts/klickspark-preview.sh stop
```

The script uses a separate Docker Compose project `klickspark-preview`, separate WordPress/database/private-storage volumes, and loopback-only ports 8091 (site) and 8092 (Mailpit). `stop` calls `down -v`, **permanently deleting the preview project's database and uploads**; never put customer data in this environment. Use only local test data. The script runs the existing Fabulous Cleaning seed file, activates the child theme, and does not activate quote/payment modules on its own. Booking and quote production readiness remain separate release gates.

**Important shared files:** the script rebuilds `generated/fabulous-cleaning` using `--force` and mounts the existing source-controlled parent theme and generated child theme. Those bind-mounted files are shared with local development; **do not run alongside another build or edit the generated files manually**. The WordPress data volumes are separate, but the generated filesystem is not. If port 8091 or 8092 is already in use, set `KS_PREVIEW_PORT` and `KS_PREVIEW_MAILPIT_PORT` to other free loopback ports.

This first step deliberately targets Fabulous Cleaning. The browser wizard's imported client configs are **not yet dynamically provisioned** by this script. No GitHub Actions run, merge, or production action launches the preview automatically. The owner must invoke it on a Docker-capable computer; it is not directly accessible from an iPhone without a separately approved secure tunnel.

Acceptance checklist: homepage renders, mobile widths, page content, accessible controls, console errors, private data isolation, and no live payments. Never claim the site was visually tested solely because CI passed.
