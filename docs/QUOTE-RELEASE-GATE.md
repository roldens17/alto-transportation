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

## Additional verified integration evidence (commit fb81fd6)

- [x] Real HTTP multipart PNG upload reaches WordPress/PHP, stores a private file, and removes it when the quote is permanently deleted.
- [x] All four workflows passed on fb81fd6: general CI, generator, MariaDB booking integration, and WordPress runtime smoke.
- [ ] Real HTTP JPEG/WebP and authorized photo-download response still require testing.

## Must verify before production

- [ ] Complete real HTTP multipart coverage for JPEG and WebP, and verify authorized download of the uploaded bytes. PNG upload and cleanup are now covered; WP-CLI cannot simulate PHP's `is_uploaded_file()` success path.
- [ ] Exercise oversized, non-image, excessive-count, partial, and failed storage uploads over HTTP; ensure no orphaned private files or incomplete quote records.
- [ ] Inject a photo-metadata database-write failure and verify files and quote are removed; the cleanup branch is not yet directly covered by a failure-injection test.
- [ ] Confirm private storage is an **absolute path outside the document root and WordPress installation**, with least-privilege filesystem ownership/permissions and no direct HTTP access. Do not store secrets or customer data in the repository.
- [ ] Decide and document quote/contact/photo retention period, deletion policy, backups, and how client data removal requests are handled. Permanent deletion triggers file cleanup; merely trashing a quote does not.
- [ ] Confirm the client-facing form includes appropriate privacy/consent language, and that notification emails contain no sensitive photos.
- [ ] Review rate limiting behind reverse proxies/CDNs and production request-size limits; the current limit uses `REMOTE_ADDR` and WordPress transients.
- [ ] Run a final CI pass on the intended merge commit and obtain explicit owner approval before merge or deployment.

## Current implementation notes

- The quote module is first-party and requires no WooCommerce or payment processor.
- Quote records use private WordPress posts; the administrative UI requires `manage_options`.
- Photo MIME checks accept JPEG, PNG and WebP; max 3 images of 5 MiB each.
- Photo storage configuration: `quotes.private_upload_dir`.
- No automated retention expiration or production deployment is implemented.
- A green CI result means the existing tests passed, **not** that a production security audit or live upload test was completed.
