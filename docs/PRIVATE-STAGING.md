# Private phone-accessible staging — implementation plan

**Status: design only; not deployed.** The current GitHub Pages demo is public and is **not** a suitable host for private client previews. A publicly accessible link with a hard-coded password in JavaScript is not authentication.

## Proposed architecture

1. A dedicated staging host runs a **separate** Docker Compose project per preview client, with test-only data and isolated volumes. Use the existing local preview workflow as the starting point after PR #17 is reviewed and merged by the owner.
2. Put the host behind a managed HTTPS identity-aware gateway such as **Cloudflare Access**. Require a named allowlist of approved reviewer emails and one-time-code or identity-provider login. Deny all unauthenticated requests. Use a distinct hostname per client, e.g. `client-preview.<approved-domain>`, only after owner approval of domain/DNS configuration.
3. The WordPress containers **must not** expose ports to the public internet. Publish on loopback only; route through an authenticated reverse proxy or authenticated tunnel. Protect all paths, including `/wp-admin`, `/wp-json`, uploads and preview pages.
4. Use **staging-only** secrets, WordPress accounts and SMTP capture; do not copy customer databases, uploads or quote attachments. Disable indexing with `X-Robots-Tag: noindex, nofollow, noarchive`, WordPress discouraging search engines, and a restrictive `robots.txt` as defense in depth (not access control).
5. Explicitly disable payment and quote intake, and do not accept real appointments. Keep any form submissions in test-only storage. Set an expiry and delete staging content after review.
6. Owner approves staging infrastructure and DNS changes separately from production. Never treat a draft PR or passing CI as authorization to deploy.

## Before a real phone preview

- [ ] Owner chooses an approved staging domain and host.
- [ ] Owner approves the staging-only deployment and any service costs.
- [ ] Reviewer email allowlist and Access policy configured and verified.
- [ ] Confirm an unauthenticated request returns denial **before** WordPress or any assets are delivered.
- [ ] Verify a permitted reviewer can open the preview from an iPhone over HTTPS.
- [ ] Verify WordPress admin, REST endpoints, uploads and alternate hostnames are not bypasses.
- [ ] Verify no live payments, quote uploads, real customer data, or indexing.
- [ ] Add per-client expiry, access revocation and cleanup instructions.

## What is available today

The public Fabulous Cleaning static demo can be used for **non-confidential design feedback**, but does not represent the latest WordPress build. PR #16 adds a local browser concept preview; PR #17 adds a Docker-hosted local WordPress preview. Neither produces a private internet URL today.

## Next engineering work

After owner selects infrastructure: implement an authenticated HTTPS staging ingress and deployment workflow with a manual approval gate, test unauthorized access and teardown, and connect the generator's per-client preview to staging. Do not embed secrets in repo or generated assets.
