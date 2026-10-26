# Local WordPress provisioning

The `scripts/klickspark-provision.sh` script provisions the **local** Fabulous Cleaning WordPress installation only. It never deploys production.

Prerequisites: Docker Compose, Node.js 22, Python 3 + PyYAML 6.0.3, and a copied `.env` with unique passwords and an admin email.

Run:

```bash
cp .env.klickspark.example .env
# Edit .env: replace ALL example passwords and admin email.
bash scripts/klickspark-provision.sh
```

The script builds the generated child theme, starts MariaDB/WordPress/Mailpit, installs WordPress if not already installed, activates the child theme, and runs the safe draft-page/service seed. The seeder preserves existing posts and never publishes prices or activates payments.

The WordPress web UI will be at `http://localhost:8080` (or your configured KS_PORT). Mailpit at `http://localhost:8025`, though outbound mail routing is not yet configured.

**Important:** Rebuilding the generated theme uses `--force` and replaces generated theme files; it does not erase WordPress database content. The WP-CLI service mounts the seeder read-only.

**Limitations:** ACF Pro and WooCommerce must be installed separately. The generated child theme currently enables booking-related code, which requires additional integration hardening. Do not treat this local setup as safe for real customers, live payments, or internet exposure. Docker runtime execution and the script itself remain unverified until tested in a compatible runner.
