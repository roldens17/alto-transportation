# Alto — WordPress build

A WordPress conversion of the marketing site in the repo root (`src/app/App.tsx`),
built on **KlickSpark Base**: one block-theme parent that holds booking logic,
one thin child theme that holds only Alto's branding and config.

```
wordpress/
  klickspark-base/            → parent theme, copied in unmodified. Never edit
                                 this per client — see its own README below.
  clients/
    alto-transportation/      → the actual per-site build
      config.php               → booking mode, lead time, flight-number rule
      functions.php             → documents which client-specific filters
                                    Alto deliberately does NOT add, and why
      theme.json                → Alto's palette/type ported from
                                    src/styles/theme.css + fonts.css
      style.css                 → theme header, font import, radius bridge
      inc/seed.php               → `wp alto seed` — populates Places, Fleet
                                    and Rates from the live site's data
```

`klickspark-base/README.md` (inside that folder) is the framework's own
documentation — architecture, the booking engine's design, the filter
reference table, and what's built vs. not. Read that first if anything below
is unclear.

## Why routes/fleet/pricing aren't in `config.php`

The React site hardcodes `ROUTES`, `VEHICLES` and `FLEET` as arrays in
`App.tsx`. In this framework that content is deliberately **not** code —
Places, Fleet and Rates are real WordPress posts (custom post types +
ACF fields), edited in wp-admin like any other content, because a locked
price list is exactly the kind of thing an ops person needs to change
without a deploy. `config.php` only holds structural decisions (booking
mode, lead time, whether flight numbers are required) — see its comments
for what's a hard fact from the site copy vs. an assumption that needs
confirming with the client.

## Setup

1. Install WordPress 6.5+, PHP 8.1+, WooCommerce, and ACF Pro.
2. Copy `klickspark-base/` and `clients/alto-transportation/` into
   `wp-content/themes/`, then activate **Alto Private Transportation**.
   wp-admin will tell you if a dependency is missing.
3. Create one WooCommerce product named "Booking": Hidden catalog
   visibility, virtual, price 0. Put its ID in `config.php`'s
   `booking.product_id`.
4. Run the seed command to create the same destinations, fleet and prices
   as the live site, instead of entering all twelve rates by hand:
   ```
   wp alto seed
   ```
   It's idempotent (matches existing rows by slug and updates them), and
   attempts to pull each vehicle's photo from the same Unsplash URLs the
   React site hotlinks — pass `--skip-images` to skip that and add photos
   yourself.
5. Create a page, assign it the "Booking page" template
   (`klickspark-base/templates/page-booking.html`).

## What this conversion carries over vs. leaves as a gap

**Carried over exactly:** the four routes and their locked one-way prices,
the three vehicles with their passenger/luggage capacities and surcharges,
the navy/gold/parchment palette, the Outfit/DM Sans/JetBrains Mono type
system, "MCO is the only pickup point" (no round trip — see the note in
`config.php` for how to turn one on later), and the "no surcharge filters"
pricing promise (documented in `functions.php` rather than coded, since
there's nothing to code).

**Not carried over — the parent theme doesn't have an equivalent yet, and
building one is new scope, not a conversion:** the bespoke hero layout, the
"NO SURGE" / "FLIGHT TRACKED" badge chips on the price display, the sticky
mobile action bar, and the three-promises / how-it-works sections as
designed. `klickspark-base`'s block templates (`parts/`, `templates/`) are
generic site-editor markup; matching the React site's actual layout means
either building it in the block editor with core blocks against Alto's
theme.json tokens, or writing custom blocks/patterns for the pieces that
need bespoke markup (the promise cards, the badge row). That's real
follow-up work, not something a config-only child theme can carry.
