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

## Active work at time of writing (2026-10-26)

**Draft PR #13:** https://github.com/roldens17/alto-transportation/pull/13

Branch: `feature/quote-request-engine`

Goal: private quote-request intake for service businesses, with customer contact/service/description, consent, limited photo uploads, admin-only review and no payment dependency.

Implemented on this branch: `wordpress/klickspark-base/inc/quotes.php`, quote form/admin JavaScript, quote module config default-off, administrator-restricted quote capabilities, upload count limit, private photo storage and administrator download handler, plus new runtime smoke-test files.

**Do not merge PR #13 yet.** At the last confirmed check, the new `KlickSpark WordPress Runtime Smoke` step failed with **“Quote module missing”**. Regular CI, generator and MariaDB checks passed. A later diagnostic commit was pushed but its outcome had not been verified at the time this handoff was authored.

### Immediate next actions

1. Check latest PR #13 GitHub Actions results and logs.
2. Diagnose why `KS_Quotes` is not loaded in WP-CLI runtime, even when `ks_module_enabled('quotes')` is true. Verify theme bootstrap, active parent/child theme, and initialization timing. Inspect `wordpress/klickspark-base/functions.php`, `inc/quotes.php`, `inc/config.php`, and `tests/php/quote-ci-enable.php`.
3. Fix the cause, not just the test assertion. Re-run the WordPress smoke test.
4. Audit quote intake and photo security: admin-only permissions, unauthorized download denial, MIME/file-size/count validation, filesystem isolation, retention and deletion cleanup, nonce/CSRF behavior, rate limiting and failure cleanup.
5. Add or improve realistic runtime tests for these cases. Mark PR ready only when checks and review are satisfactory; **owner performs merge**.
6. After #13, plan the next unified client-facing business administration experience and further premium design improvements.

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

> You are continuing KlickSpark Website Factory in `roldens17/alto-transportation`. Read `AGENTS.md` and the current open PRs. I work from an iPhone; implement changes directly through GitHub and CI where possible. Never merge or deploy without my explicit approval. First verify PR #13's current status and fix any failing WordPress quote runtime tests, then continue security and quality hardening. Give me direct PR links and truthful test results.

## Maintenance rule

**Update this handoff whenever an important PR is merged, a milestone changes, a new deployment workflow is introduced or an important blocker is resolved.** Date the update and distinguish verified status from planned work.
