<?php
/**
 * Client overrides. Use the parent's filters — never edit the parent.
 *
 * There is no `ks/booking/quote_lines` filter here on purpose. Compare this
 * to clients/example-transfers, which adds a late-night surcharge — Alto's
 * hero copy promises the opposite: "No surge pricing. No traffic
 * multipliers. No late-night spikes." The rate table (entered as Rates in
 * wp-admin, see wordpress/README.md) is the entire price. Anyone tempted to
 * add a time-of-day or demand filter here should talk to the client first;
 * it would directly contradict the site's core pitch.
 *
 * Likewise there is no `ks/booking/validate` blackout-date filter. The trust
 * bar advertises "24/7 Dispatch" — every hour of every day is bookable
 * online, so the base engine's lead-time check is the only gate.
 *
 * The one thing the copy does promise — "if your inbound flight is delayed,
 * your chauffeur adjusts automatically at zero extra cost" — needs no filter
 * either: the engine already never re-prices a confirmed booking, so "zero
 * extra cost" is just correct behaviour, not a rule to add.
 */

defined( 'ABSPATH' ) || exit;

require_once get_stylesheet_directory() . '/inc/seed.php';
