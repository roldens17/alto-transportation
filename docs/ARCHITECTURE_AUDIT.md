# KlickSpark Website Factory — Architecture Audit

Status: initial source audit (not a production security certification)
Repository: roldens17/alto-transportation
Working branch: feature/klickspark-generator

## Existing architecture

- `wordpress/klickspark-base/` is a WordPress block parent theme (v0.1.0).
- `wordpress/clients/alto-transportation/` is the Alto child theme with configuration and WP-CLI seeding.
- `inc/config.php` deep-merges defaults with child `config.php` and exposes `ks_config()`.
- `inc/cpt.php` registers configurable post types.
- `inc/acf.php` and slot mode register ACF fields.
- `inc/booking/engine.php` provides booking request and validation logic.
- `inc/booking/rest.php` exposes route booking endpoints.
- `inc/booking/mode-slot.php` calculates service/resource availability.
- `inc/booking/store.php` persists appointment holds in a custom SQL table.
- `inc/booking/woo-slot.php` bridges appointments to WooCommerce.
- `inc/booking/intake.php` and `review-queue.php` support nonpayment intake.
- `blocks/` provides booking, appointment and legal notice blocks.
- Root `package.json` and `.github/workflows/ci.yml` serve the existing React/Vite site; they do not validate the WordPress subsystem.

## Reusable assets

Configuration resolution, Gutenberg block registration, scheduling and time-off calculation, WooCommerce order integration, ACF field declarations, appointment persistence, and the WP-CLI seeding approach. Preserve behavior during extraction.

## Alto-specific coupling

The child theme config, MCO-only route model, destination/fleet/rate seed data, transport pricing and marketing visuals belong to Alto. They must not become shared defaults for cleaning or other verticals.

## Prioritized risks and gaps

### P0 — must fix before paid booking launch

1. **Overlap concurrency**: `KS_Slot_Store::hold()` performs a check then insert. Its unique index on practitioner/start/status does not prevent intervals with different starts from overlapping. Introduce a resource-scoped database lock or transaction strategy and concurrency tests.
2. **Buffer mismatch**: `KS_Slot_Engine::slots_for_day()` checks service duration plus buffer, while `KS_Slot_Woo::add_to_cart()` holds duration alone. Reserve the same occupied interval throughout.
3. **Payment and hold lifecycle**: confirm payment success, hold expiration, order creation and duplicate callbacks are idempotent; never confirm a booking from unverified client state.
4. **Server-authoritative pricing**: recalculate the payable quote at checkout and reject stale or tampered cart data.
5. **Input and permission checks**: verify valid services/resources and permitted booking selections on every endpoint.

### P1 — required before reusable rollout

6. **Public API abuse controls**: `inc/booking/rest.php` comments imply nonce-based rate limiting; nonces are not rate limits. Implement explicit throttling and sensible request bounds.
7. **Theme/plugin coupling**: business logic currently loads from `functions.php`. Move it to a versioned plugin with compatibility safeguards, activation migrations and no double-loading.
8. **Dependency notices**: `inc/setup.php` demands WooCommerce/product ID whenever booking is enabled, even when slot intake is configured for `payment=none`.
9. **Missing WordPress CI**: root workflow only runs JS lint/build; add PHP lint, WordPress tests, booking concurrency tests and generator tests.
10. **Unimplemented mode**: `unit` is documented in defaults but is not bootstrapped as an actual booking mode.
11. **Domain naming**: practitioner/patient terminology should be abstracted to resource/customer for cleaning, without breaking persisted data.
12. **Production readiness**: backups, migrations, audit logging, transactional email, privacy and retention controls, payment-gateway setup and deployment rollback require validation.

## Migration strategy

1. Preserve the original `wordpress/` tree while introducing the internal generator separately.
2. Generate child themes from client YAML and explicit industry defaults; do not copy transport business rules.
3. Add tests and compatibility fixtures for Alto before relocating PHP classes.
4. Extract shared functionality to `packages/klickspark-core/` behind an opt-in bootstrap; keep one canonical implementation and prevent double registration.
5. Move the base theme to `packages/klickspark-base/` only after a verified compatibility build.
6. Implement cleaning-specific service attributes, crew availability, server-side pricing, checkout and owner workflows.
7. Gate live booking and production deployment on security and end-to-end tests.

## Current verification limits

This audit is based on GitHub source inspection, not a running WordPress instance. No database, payment gateway, browser or production deployment was tested. The existing React application and WordPress site have not been modified by this document.
