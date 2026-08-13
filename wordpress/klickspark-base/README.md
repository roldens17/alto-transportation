# KlickSpark Base

A reusable WordPress foundation for client sites: one block-theme parent that
holds all the logic, one thin child theme per client that holds only identity.

Stack assumptions: WordPress 6.5+, PHP 8.1+, WooCommerce, ACF Pro.

```
klickspark-base/          → parent theme. Install once, never edit per client.
  functions.php           → bootstrap
  theme.json              → neutral tokens; the child overrides them
  inc/config.php          → the contract. Defaults + child config merge.
  inc/setup.php           → theme supports, performance floor, dependency guard
  inc/cpt.php             → post types + taxonomies generated from config
  inc/acf.php             → field groups in PHP, version-controlled
  inc/blocks.php          → auto-registers every folder in /blocks
  inc/booking/engine.php  → validation + pricing. No Woo, no HTML, no globals.
  inc/booking/woo.php     → cart, checkout, order line meta
  inc/booking/rest.php    → /wp-json/ks/v1/places, /quote, /booking
  blocks/booking-form/    → block.json + render.php + view.js + style.css
  templates/, parts/      → block templates

clients/example-transfers/ → child theme showing the whole per-client surface
  config.php              → in practice, this file IS the build
  functions.php           → client pricing rules via filters
  theme.json              → palette + type
  style.css               → whatever theme.json cannot express
```

## Why it's split this way

The engine takes an array in and returns a quote out. It never touches
WooCommerce, never renders markup, never reads `$_POST`. That's what lets you
reuse it — and it means you can unit test pricing without booting WordPress.

WooCommerce integration uses **one hidden container product** whose price is set
from the engine at cart-calculation time. No product-per-route, no stock
management, no rate change breaking historical orders.

## Per-client setup

1. Install the parent + child, activate the child.
2. Install ACF Pro and WooCommerce. Admin will tell you what's missing.
3. Create one WooCommerce product: name it "Booking", set it to Hidden
   catalog visibility, virtual, price 0. Put its ID in `config.php`.
4. Add Places (mark each as pickup / dropoff, set type to airport where
   relevant), then Fleet with capacities, then Rates.
5. Create a page, assign the "Booking page" template.

Rates with **Also applies in reverse** on mean you enter each pair once.
Rate count = places² × vehicles at worst, so start with zones rather than
individual hotels and refine later.

## Extending without forking

Every client-specific rule is a filter in the child's `functions.php`:

| Filter | Use it for |
|---|---|
| `ks/config` | anything computed at runtime |
| `ks/booking/validate` | blackout dates, service areas, group minimums |
| `ks/booking/price/{mode}` | replace pricing wholesale (distance API, per-km) |
| `ks/booking/quote_lines` | surcharges, discounts, fees, taxes |

The child theme in `clients/example-transfers` shows two of these working.

## What's built vs. what isn't

Built and working: config resolution, content model, ACF groups, route-mode
pricing with reverse lookup and object caching, capacity filtering, validation
with field-level messages, vehicle options endpoint, Woo cart/checkout/order
meta, the booking block with progressive enhancement, accessibility floor
(labels, `aria-live`, focus-visible, reduced motion), mobile layout.

Also built: **`slot` mode** — availability computed from working hours minus
time off minus existing appointments, a custom `wp_ks_appointments` table with a
unique index that loses double-booking races cleanly, 15-minute holds that
expire on cron, and re-validation at checkout so a lapsed hold surfaces as a
notice instead of a double-booked practitioner.

Not built yet, in the order I'd do them:

1. **Multi-stop with real intermediate places** — right now `stops` is a
   count and a flat fee. Global DT collects actual stop locations.
3. **Admin dispatch view** — a filterable list of upcoming bookings for route
   mode. `KS_Slot_Store::upcoming()` and the review queue already do this for
   slot mode; route mode still needs it.
4. **Confirmation email template** — Woo sends the default; the summary lines
   are there, they just need a template override.
5. **Translation** — strings are wrapped for `ks-base` but there's no `.pot`
   yet, and route/place names need Polylang or TranslatePress registration.
6. **Tests** — the engine is pure enough for PHPUnit. Do this before client
   three, not after.

## Honest caveat on the stack choice

Block theme + WooCommerce is the one friction point in these picks. Woo's cart
and checkout have block versions now, but its templates are still PHP-first, so
expect to override a few `woocommerce/` templates in the child rather than
editing them in the site editor. It works — it's just not as clean as the rest.


---

## Verticals

Each folder in `clients/` is a working configuration, not a copy of the parent.

| Client | Mode | Content model | What changes |
|---|---|---|---|
| `example-transfers` | `route` | places, fleet, rates | zone pricing, night surcharge |
| `ridgeline-physio` | `slot` | services, practitioners, time off | intake notice rule, lunch closure |
| `puente-immigration` | `slot`, no payment | matter types, attorneys, case stages | conflict-check gate, language-filtered availability, bar notices |

### Puente Immigration Law (concept build — lead portfolio piece)

A fictional trilingual immigration firm in Orlando. This is the vertical to sell
first: no HIPAA gate, and state bar advertising rules are a copy-and-config
problem rather than an infrastructure one.

It exercises three things the other two don't:

- **`payment: none`** — consultations are not paid online, so the flow is
  engine → hold → intake form → email, with WooCommerce entirely absent. This is
  the proof that keeping the engine free of Woo was worth it.
- **`conflict_check: true`** — a submitted request reserves the slot as
  `pending` and lands in an admin review queue. A human clears it or flags a
  conflict; either way the enquirer gets a real email. A firm cannot confirm a
  consultation before checking it isn't adverse to an existing client.
- **`compliance`** — jurisdiction and no-relationship notices come from config,
  render as a block, and are force-appended to the footer if an editor deletes
  the block. Compliance should not depend on someone remembering.

Also: availability is filtered by consultation language, so someone who needs
Haitian Creole only ever sees times from attorneys who actually speak it. That
is better than booking them and reassigning later.

**Design direction** — the firm's world is forms, receipt numbers, and waiting.
So the site is built from the docket.

- **Palette** — Paper `#F4F2F6`, Ink `#16162A`, Indigo `#2B2A6E`, Stamp
  `#B0316A`, Surface `#E4E1EA`. Deliberately not the mahogany-and-gold every
  legal template reaches for. The stamp magenta is rubber-stamp ink and appears
  only on form numbers, the disclaimer, and the single CTA.
- **Type** — Instrument Serif for display (editorial and contemporary, not
  old-money), Public Sans for body, JetBrains Mono for every form number, month
  range, fee, and bar number. This inverts the clinic's pairing on purpose.
- **Signature** — the case track: a docket rail where form numbers are stations
  and honest month ranges sit on the right. Most firm sites hide processing
  times; this one leads with them, which is also the positioning.
- **The risk** — the required disclaimer is rendered as a rotated rubber stamp
  and placed in the hero, not the footer. It stops being fine print and becomes
  the most credible thing on the page.
- **Language bar first** — above the logo, because for a large share of visitors
  language is the first barrier, not navigation.

`puente-mockup.html` is a static single-file render. Open it in a browser.

### Ridgeline Sports Physiotherapy (concept build)

A sports rehab clinic. Demonstrates that switching vertical is a config change:
`fleet` and `places` off, `mode` set to `slot`, four post types relabelled.

**Design direction** — the subject is measurable recovery, so the site is built
around the goniometer, the arc-shaped protractor a physio uses to measure your
range of motion.

- **Palette** — Bone `#F1F2EE`, Ink `#0E1A24`, Measured navy `#1B3A6B`,
  Needle `#D6402C`, Surface `#DDE3E1`. The red appears only where something is
  being measured or committed to: the needle, the selected time, the one CTA.
- **Type** — Archivo for display (technical, tight), Newsreader for body (a
  serif keeps a clinic human rather than gym-branded), IBM Plex Mono for every
  number. Degrees, prices, times and hours are all data and all set in mono
  with tabular figures.
- **Signature** — the arc. It is the hero graphic (a sweep from 52° to 128°
  over eight weeks), the section divider, and the booking step indicator.
- **Copy stance** — every service and condition publishes its duration and
  typical course length. The differentiator is candour about time.

`ridgeline-mockup.html` is a static single-file render of the homepage. Open it
in a browser; it needs no WordPress. Use it as the portfolio piece, labelled as
a concept build.


## Before you take a real law firm client

- **Bar rules are per state.** Florida requires specific handling of
  testimonials, "specialist" claims, and past results. The notices here are
  plausible, not vetted. Have the firm's own compliance person approve the
  final copy — they carry the liability, and they will expect to be asked.
- **The intake form collects sensitive facts.** Deadlines, case numbers, and
  "what is happening" are privileged-adjacent from the moment they arrive.
  Encrypt at rest, restrict admin access, set a retention window, and do not
  route submissions through a third-party form service.
- **Never state a filing deadline yourself.** Publishing processing ranges is
  good positioning; a countdown to someone's deadline is practising law.
- **Two languages is a content commitment, not a plugin.** Three is a
  commitment the firm has to staff. Confirm they can answer a Creole voicemail
  before you ship a Creole booking path.
