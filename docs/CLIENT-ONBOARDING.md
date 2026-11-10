# KlickSpark Website Factory — Client Onboarding Playbook (Draft)

Internal agency workflow. One client = one WordPress installation, unique branding/content, separate configuration, separate approvals. Never expose one client's data to another. No SaaS dashboard or shared multi-tenant WordPress database.

## Stage 1 — Qualify and scope
Collect the business name, industry, primary goal (calls, quote requests, booking requests), service area, target customers, services, and one clear call to action. Confirm who owns the domain, hosting, copy, images and ongoing updates. Agree on a written scope, timeline, revision rounds and price **before** production work. Do not promise online payments or confirmed bookings unless those integrations are specifically delivered and verified.

## Stage 2 — Client intake (do not commit private information)
- Brand: legal/public business name, preferred URL, logo, brand colors, reference sites, photography rights.
- Contact: approved public phone/email, service area, opening hours, social links.
- Content: approved hero headline, services, service descriptions, testimonials with permission, FAQ, business policies.
- Workflow: calls, email, quote request, or appointment request; who responds and within what time.
- Privacy: privacy notice owner, form data collected, photo retention and deletion, consent language.
- Operations: hosting/domain administrator, WordPress administrator, backup contact, maintenance owner.
- Launch: owner approval for copy, design, forms, accessibility review, security and go-live.

Store sensitive intake and access credentials outside Git. Client YAML should contain only nonsecret, approved configuration and public business details.

## Stage 3 — Build
1. Scaffold a unique client config using `node generator/src/cli.mjs new <slug> --industry cleaning` when that industry applies. Other industries require appropriate templates and validation.
2. Edit `clients/<slug>/site.yaml` and `clients/<slug>/theme/` source, **not generated artifacts**.
3. Validate with `node generator/src/cli.mjs validate <slug>`; build with `node generator/src/cli.mjs build <slug> --force`.
4. Build a distinct mobile-first design: legible type, consistent spacing, accessible contrast, real approved content, useful service sections, clear contact CTA, and fast-loading appropriately licensed images.
5. Keep `modules.payments: false` unless a separate approved payment project is completed. Enable quotes only after per-host storage, privacy, retention and authorization requirements are met; see `docs/QUOTE-RELEASE-GATE.md` on the quote feature branch.

## Stage 4 — Acceptance
- Mobile, tablet, desktop; keyboard navigation, labels, contrast, image alt text and reduced-motion preferences.
- Contact links, navigation, 404 page, service information, business hours and public contact details.
- Form spam resistance, validation, privacy notice, error states, notification delivery and responsible recipient.
- Test actual booking/quote workflows in the target environment; generator output alone does not make them production-ready.
- TLS, least-privilege accounts, backups/restore, update ownership, search-engine settings, analytics consent if applicable.
- Client approves final content and launch checklist in writing.

## Stage 5 — Handoff and care
Provide site URL, account/access handoff via a secure channel, how to edit services and content, form response process, backup/update ownership, support boundaries, and renewal/maintenance terms. Document a rollback plan and a post-launch smoke check.

## Showcase: Fabulous Cleaning
Initial design example only, **not a live client launch**. Source: `clients/fabulous-cleaning/`. Its config currently disables payments and quotes. Booking support requires separate production readiness checks. Replace placeholder contact details and claims with approved business information before any client launch.

## Current phase boundaries
This playbook is an onboarding plan, not automated provisioning or deployment. The generator currently builds child themes but does not create WordPress sites, publish pages, install plugins, configure payments, or deploy. Any implementation of these steps needs its own reviewed PR and tests.
