# KlickSpark Website Factory — AI Agent Handoff

> **Read this file before changing code.** This repository also contains an older Alto Transportation pitch/frontend project. Do not assume that its root README describes the current WordPress Website Factory.

## Mission and ownership

Build **KlickSpark Website Factory**, a reusable **internal** WordPress website generation and deployment workflow for **KlickSpark Media LLC**. It helps the agency build distinct, polished business websites for individual clients. **It is not a SaaS product, theme marketplace, or a generic starter kit for resale.**

Repository: https://github.com/roldens17/alto-transportation

The owner frequently works **from an iPhone**. Prefer making and reviewing changes through GitHub branches, commits, pull requests and GitHub Actions; do not require a local computer for routine progress. **The owner merges PRs. Agents must not merge PRs, deploy to production, purchase services, or change real customer data without explicit approval.**

## Architecture and important paths

| Path | Purpose |
| --- | --- |
| `wordpress/klickspark-base/` | Shared WordPress parent theme, configuration, content types, booking and quote modules |
| `wordpress/clients/alto-transportation/` | Existing transportation-specific client implementation |
| `wordpress/clients/fabulous-cleaning/` | Fabulous Cleaning client source/seed |
| `clients/` | Generator client YAML/config inputs |
| `generator/src/cli.mjs` | Client scaffold, validate and build CLI |
| `generator/test/` | Generator tests |
| `generated/<client>/` | Generated artifacts; not the source of truth |
| `scripts/klickspark-provision.sh` | Disposable/local WordPress provisioning |
| `docker-compose.klickspark.yml` | Local WordPress, MariaDB, WP-CLI and Mailpit services |
| `tests/php/` | PHP and WordPress integration smoke tests |
| `.github/workflows/` | CI, WordPress runtime, MariaDB integration and preview automation |
| `docs/` | Architecture, generator, provisioning, local development and CI documentation |

Some existing docs were written during earlier phases and may be outdated. **Verify behavior against current source code and passing tests before making claims.**

## How to work

1. Read this file, `docs/GENERATOR.md`, the relevant module and recent open PRs before proposing changes.
2. Check the active branch and CI. **Do not assume a prior conversation's PR state is still current.**
3. Make focused changes in a feature branch or the already-approved working PR. Do not edit unrelated client websites.
4. Include tests for new functionality, especially PHP/WordPress runtime, privacy, file uploads, permissions and bookings.
5. Push commits and give the owner a direct PR link, a concise summary and an accurate CI status.
6. Keep unfinished/security-sensitive PRs as **draft**. Never claim tests passed until GitHub Actions confirms them.
7. Ask before merging, releasing, deploying, modifying live sites, charging clients or using production credentials.

## Current functional direction

- Generator creates per-client child-theme artifacts from client configuration, with a reusable parent theme.
- Local disposable WordPress provisioning and CI exist.
- Fabulous Cleaning is the initial cleaning-business showcase.
- Business features are being built as **first-party, config-driven modules**, including appointment/booking intake without upfront payment and a private quote-request intake.
- Do not introduce WooCommerce as a dependency for **no-payment** quote or appointment requests.
- Different clients should have their own content, branding, services, workflows and WordPress installations.
- Prioritize premium visual quality, accessibility, mobile layouts, fast loading and maintainable modules.
- The static Fabulous Cleaning preview has been published at https://roldens17.github.io/alto-transportation/fabulous-cleaning/ ; check whether it is still current before presenting it as the latest design.

## Verified status (2026-11-10)

- PR #13 was merged on 2026-10-27. GitHub confirms all four pull-request workflows passed on its final commit `195e632`: regular CI, Generator, MariaDB Booking Integration and WordPress Runtime Smoke. The earlier “Quote module missing” blocker is resolved.
- Quotes remain disabled by default. Production activation still requires the host-specific storage, privacy, retention and ingress checks in `docs/QUOTE-RELEASE-GATE.md` and explicit owner approval.
- PR #20 was squash-merged into `main` as `0b606ef` on 2026-11-10 after explicit owner authorization. All four workflows passed on its final code commit `8d2d53c`. Earlier milestones include safe opt-in scaffolding (#15) and the disposable local WordPress preview (#17).
- Open PRs checked on 2026-11-10: onboarding playbook #14, setup wizard #16, private staging plan #18 and home-lab staging plan #19. They are separate from the booking corrections below.

### Booking review corrections merged (2026-11-10)

Merged PR #20: https://github.com/roldens17/alto-transportation/pull/20

Source branch: `fix/booking-intake-review`. The following corrections are merged:

1. Validate no-payment submissions against the same calendar as paid bookings, including published service/crew identity, assignments, working hours, time off and exact offered starts; reserve turnaround buffers.
2. Honor the booking module switch during slot bootstrap, route registration and form rendering. Expose payment endpoints only when their mode and WooCommerce dependency are available.
3. Render priced no-payment appointments without WooCommerce and remove inappropriate payment dependency notices.
4. Restrict enquiry access to administrators, including legacy published enquiries; create new enquiries as private records.
5. Fall back to the administrator email when the business notification recipient is empty or invalid.

Verified evidence: all four workflows passed on `8d2d53c`: application CI, Generator (including PHP lint and contract tests), MariaDB Booking Integration and WordPress Runtime Smoke. Runtime coverage includes disabled modules, unavailable payment dependencies, calendar bypass rejection, turnaround buffers, enquiry role permissions, failed-transition cleanup and default/custom/invalid notification recipients. Local generator tests also pass. CI uses a test-only post-meta adapter in place of licensed ACF Pro; actual ACF/host acceptance remains separate.

### Local deployment verified (2026-11-10)

The owner explicitly requested merge and deployment, then selected Docker on this computer. The merged source runs through `scripts/klickspark-preview.sh start fabulous-cleaning` in the isolated `klickspark-preview-fabulous-cleaning` Compose project. WordPress: `http://localhost:8091`; Mailpit: `http://localhost:8092`. The Fabulous Cleaning homepage rendered in the browser. Existing Docker volumes were preserved.

Local credentials are generated in the workspace `.env` (mode 0600), excluded by `.gitignore`; never include their values in commits or logs. The admin username is `kslocal`. The standard seed keeps content in draft; quotes and payments are disabled. No public hosting, licensed ACF installation or customer-data migration was performed. The stack remains running for the owner. `stop` permanently deletes this preview's isolated volumes; do not stop/delete it without an explicit request.

Future merges and deployments still require owner authorization. Booking production readiness requires client-specific setup, licensed ACF integration, realistic browser/hosting checks and approval.

## Local / CI commands

```bash
python3 -m pip install PyYAML==6.0.3
node generator/src/cli.mjs validate fabulous-cleaning
node generator/src/cli.mjs build fabulous-cleaning --force
node --test generator/test/*.test.mjs

# Disposable local stack only; read docs/PROVISIONING.md first
cp .env.klickspark.example .env
# Replace local secrets in .env before provisioning
bash scripts/klickspark-provision.sh
```

Required for generator: Node.js 22, Python 3, PyYAML 6.0.3. WordPress stack uses Docker Compose. Never run destructive volume cleanup against customer data.

## Security and quality guardrails

- No secrets in Git, client YAML, frontend assets, logs or PR comments.
- Never expose customer quote PII or photos in public endpoints, media libraries or unauthenticated admin views.
- Quote photos must be stored outside all publicly served roots, with authenticated/authorized download controls.
- Validate and sanitize server-side; escape on output; verify nonces for privileged changes.
- Avoid changing generated output by hand when a source template/config should be changed.
- Keep client-specific logic out of the reusable parent unless it is intentionally configurable.
- Ensure compatibility with the project's actual PHP and WordPress runtime versions.
- Treat older docs as historical context if contradicted by tested current code.
- Do not promise that booking, payments, or quote uploads are production-ready without end-to-end evidence.

## Suggested prompt for the next AI agent

> You are continuing KlickSpark Website Factory in `roldens17/alto-transportation`. Read `AGENTS.md` and the current open PRs. I work from an iPhone; implement changes directly through GitHub and CI where possible. Never merge or deploy without my explicit approval. First check the current booking-fix PR and its GitHub Actions results, then continue security and quality hardening. Give me direct PR links and truthful test results.

## Maintenance rule

**Update this handoff whenever an important PR is merged, a milestone changes, a new deployment workflow is introduced or an important blocker is resolved.** Date the update and distinguish verified status from planned work.


## Local booking visibility work (2026-11-10)

The owner reported booking was not visible. The prior local preview had no homepage booking link, a draft booking page, draft services, no crew calendar and no ACF. Branch `fix/local-booking-preview` adds homepage navigation, a branded booking template, native WordPress metadata reads for scheduling (preserving ACF reads for ACF-authored records), and explicit `KS_PREVIEW_BOOKING_DEMO=1` loopback-only sample data. The default preview/provision seed remains draft-only. Rebuilt theme mounts are refreshed by recreating the WordPress container while preserving volumes. These changes are local/proposed until their PR is merged; do not assume the previous merge authorization covers new PRs.

Use only test contact details in the local booking demo. Do not delete the preview volumes without an explicit request. Check the new PR's final CI results and recorded browser evidence before making readiness claims.

Local browser evidence: selected Standard Cleaning (Demo), loaded real availability, and submitted `Demo Booking Test` using an example.test email. The UI returned a pending-approval receipt. A separate read checks the private enquiry and 90-minute buffered pending reservation. Sample records are retained in the isolated local preview for review; no real appointment was arranged. Local generator, PHP field compatibility, slot locking and checkout-hold checks passed. GitHub CI for this branch must still be checked.
