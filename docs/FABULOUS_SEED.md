# Fabulous Cleaning content bootstrap

The first content automation is intentionally conservative. It creates **draft** pages (Home, Services, About, Contact, Booking) and draft service records (Standard Cleaning, Deep Cleaning, Move-In / Move-Out Cleaning) without inventing prices, business details, service duration, or crew schedules.

Run from a WordPress environment with the repository accessible:

```sh
wp eval-file wordpress/clients/fabulous-cleaning/seed.php
```

The command uses the existing `ks_service` custom post type when available. Re-running it preserves existing content, even if a site editor has changed it. Nothing is automatically published. It never changes WooCommerce products or gateways.

**Before launching:** obtain approved business copy, verified contact information, service coverage, durations, prices, crew availability, cancellation terms and payment policy. Verify ACF Pro and WooCommerce integrations. Add tests for booking concurrency and payment lifecycle before accepting orders.

This is not yet wired into the Docker WP-CLI service because the repository is not mounted into that container. A later PR will add an explicit read-only scripts mount and a guarded setup command.
