# KlickSpark private home-lab staging (proposed, not deployed)

**Decision:** Use the owner's Proxmox home lab rather than a paid VPS. This is a planning document only. No router, DNS, Cloudflare, Proxmox, customer, or production changes have been made.

## Recommended layout

- Create a dedicated, unprivileged Ubuntu Server VM on Proxmox. Starting allocation: **2 vCPU, 4 GiB RAM, 40 GiB disk** for one or two lightweight active WordPress previews. Reassess actual load and available RAM before allocating more.
- Install supported Docker Engine and Docker Compose, run a separate preview stack for each client, and keep MariaDB, WordPress, and uploads in isolated Docker volumes. Bind WordPress HTTP only to VM loopback.
- Use a **Cloudflare Tunnel** with outbound-only connection from the VM, backed by **Cloudflare Access** authentication on a staging subdomain of an owner-controlled domain. Allowlist specific reviewer identities. Enforce Access on the entire hostname, including `/wp-admin`, `/wp-json`, and media routes.
- **Do not forward router ports 80, 443, 3306, 22, or Proxmox 8006.** Keep the Proxmox management UI off the public internet. For administration, use a separate private management channel such as Tailscale with MFA.
- Keep a separate set of staging-only credentials, disable indexing, and use synthetic content only. Never load production databases or quote attachments. Booking, payments, and quote submissions must not be enabled for real customers on staging.
- Set resource limits and monitor disk space, RAM, Docker volumes, and certificate/access health. Configure a way to revoke Access users and shut down/erase each preview.
- Home internet or power outages will make the staging site unavailable. Home hosting avoids a VPS fee but may incur domain/optional service costs and uses existing electricity/hardware.

## Phased approval gates

1. Owner confirms a domain/subdomain, Cloudflare account availability, and permitted reviewer email identities.
2. Verify draft PRs #16/#17 and dependencies; owner reviews and merges only when ready.
3. Build a repeatable VM provisioning guide and Cloudflare Tunnel/Access configuration with **no secrets in Git**.
4. Test unauthenticated denial on every route, allowed reviewer iPhone access, HTTPS, WordPress API, media, noindex, and client isolation. Confirm no origin bypass.
5. After explicit owner approval, configure home lab and DNS. No deployment until then.

## Local preview baseline

PR #17 introduces `scripts/klickspark-preview.sh` for local Docker previews; it is not yet a secure internet-facing service. Do not expose that local Docker port directly. The public GitHub Pages Fabulous Cleaning demo is not an authenticated WordPress preview.

## Proposed next deliverable

A reproducible Ubuntu VM setup and an outbound-only `cloudflared` container with Cloudflare Access policy verification and a single staging hostname, after confirming the owner's domain and Access account.
