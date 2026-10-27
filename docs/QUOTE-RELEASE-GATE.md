# Quote Request Engine — Release Gate (PR #13)

Status: **NOT APPROVED FOR PRODUCTION**. Keep PR #13 draft until the owner reviews the evidence and explicitly approves a merge. This checklist concerns the quote module, not the readiness of booking or the Website Factory as a whole.

## Verified in automated WordPress smoke tests (commit 85fac3d)

- [x] Reject invalid REST nonce and malformed upload/error input.
- [x] Create private quote record for a valid no-photo request.
- [x] Reject anonymous photo downloads.
- [x] Reject paths outside private storage, including a file in another quote's directory.
- [x] Reject tampered MIME metadata and malformed photo metadata.
- [x] Delete files in the quote's dedicated directory when its post is permanently deleted, preserving unrelated files.
- [x] General CI, generator, MariaDB integration, and WordPress smoke workflows passed on 85fac3d.

## Additional verified integration evidence (through commit 631b8d3)

- [x] Real HTTP multipart PNG upload reaches WordPress/PHP, stores a private file, and removes it when the quote is permanently deleted.
- [x] All four workflows passed on fb81fd6: general CI, generator, MariaDB booking integration, and WordPress runtime smoke.
- [x] Real HTTP rejection of disguised non-image content, more than three photos, and a photo exceeding 5 MiB; no quote record created. 
- [x] Real HTTP anonymous download attempt denied. 
- [x] All four workflows passed on 631b8d3. 
- [x] Authenticated administrator photo download over real HTTP returned the original PNG bytes, with anonymous access denied (green runtime workflow on b83d672). JPEG/WebP remain unverified over real HTTP.

## Must verify before production

- [ ] Complete real HTTP multipart coverage for JPEG and WebP. Authorized PNG download bytes are verified; WP-CLI cannot simulate PHP's `is_uploaded_file()` success path.
- [x] Exercise oversized, non-image, and excessive-count uploads over HTTP; each rejected before quote creation. 
- [ ] Exercise partial and failed storage uploads over HTTP; ensure no orphaned private files or incomplete quote records.
- [x] Inject a photo-metadata database-write failure over real HTTP and verify files and quote are removed (passed on 4fd548c).
- [ ] Confirm private storage is an **absolute path outside the document root and WordPress installation**, with least-privilege filesystem ownership/permissions and no direct HTTP access. CI uses `/var/ks-private` in a dedicated Docker volume; each production host must be checked separately. Do not store secrets or customer data in the repository.
- [ ] Decide and document quote/contact/photo retention period, deletion policy, backups, and how client data removal requests are handled. Permanent deletion triggers file cleanup; merely trashing a quote does not.
- [ ] Confirm the client-facing form includes appropriate privacy/consent language, and that notification emails contain no sensitive photos.
- [ ] Review rate limiting behind reverse proxies/CDNs and production request-size limits; the current limit uses `REMOTE_ADDR` and WordPress transients.
- [ ] Run a final CI pass on the intended merge commit and obtain explicit owner approval before merge or deployment.

## Current automated evidence (2026-10-27)

- [x] All four workflows passed on `90f0f1d`: general CI, generator, MariaDB integration, and WordPress runtime smoke.
- [x] WordPress runtime renders the cleaning-specific service dropdown and generic reusable input.
- [x] Fabulous Cleaning generator produces `booking.payment = none`, and payments are disabled in its source configuration.
- [ ] A passing CI workflow does **not** verify private storage configuration, data retention, proxy controls or privacy notices on a production host.

## Current implementation notes

- The quote module is first-party and requires no WooCommerce or payment processor.
- Quote records use private WordPress posts; the administrative UI requires `manage_options`.
- Photo MIME checks accept JPEG, PNG and WebP; max 3 images of 5 MiB each.
- Photo storage configuration: `quotes.private_upload_dir`.
- No automated retention expiration or production deployment is implemented.
- A green CI result means the existing tests passed, **not** that a production security audit or live upload test was completed.

## Production operator decisions (required per client)

1. **Storage:** Set `quotes.private_upload_dir` to an absolute, writable, non-public directory outside the WordPress tree and web server document root. Give the PHP service account access only to that directory. Verify requests to a guessed photo URL cannot serve files. Back up the directory securely if quote records are backed up.
2. **Retention:** Client owner must choose how long submitted contact details, descriptions, and photos are kept, how to honor deletion requests, and how backups expire. The application currently has **no scheduled purge**. Trashing a quote does **not** delete its photos; permanent deletion does.
3. **Consent and notice:** Publish a client-specific privacy notice describing collection, access, retention, and contact purpose. The existing consent checkbox is not a substitute for the notice. Review outgoing notifications for unnecessary personal data or attachments.
4. **Ingress:** Verify PHP `upload_max_filesize` >= 5 MiB and `post_max_size` comfortably exceeds 15 MiB plus form overhead. Set a web-server/CDN request-size cap, review proxy IP handling and throttling, and ensure TLS is enabled.
5. **Permissions and recovery:** Limit WordPress administrator access, use backups with access controls, and test a restore and deletion procedure on the actual host.
6. **Approval:** Record each client's storage location, privacy notice, retention decision, hosting/proxy configuration, test evidence, and explicit owner approval before enabling the module in production. Do not commit private paths containing credentials or customer data.
