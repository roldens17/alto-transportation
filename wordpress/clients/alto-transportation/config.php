<?php
/**
 * Alto Private Transportation — client config.
 *
 * Ported 1:1 from the marketing site (src/app/App.tsx in the repo root):
 * fixed-price, flight-tracked airport transfers out of MCO. No round trip,
 * no multi-stop, no surge — those aren't missing features, they're the
 * product's whole pitch ("the price you see is the price you pay").
 */

return [
	'modules' => [
		'booking'    => true,
		'fleet'      => true,
		'places'     => true,
		'activities' => false,
	],

	'booking' => [
		'mode'                => 'route',
		'currency'            => 'USD',

		// The live site only ever sells one-way MCO → destination. Leave both
		// off rather than ship a half-supported round trip; flip them on
		// (and mark MCO as a dropoff in seed.php) if that ever becomes a
		// real product, since the engine already supports it.
		'round_trip'          => false,
		'round_trip_modifier' => 2.0,   // unused while round_trip is off
		'allow_stops'         => false,
		'stop_fee'            => 0.00,

		// Assumption: not specified by the marketing copy. Kept short because
		// live FAA flight tracking is what removes the need for a big buffer
		// — confirm the real number with dispatch before launch.
		'min_lead_hours'      => 2,
		'max_days_ahead'      => 365,
		'time_step_minutes'   => 15,

		'product_id'          => 0,      // set after creating the hidden "Booking" WooCommerce product
		'require_flight_no'   => true,   // "we track your arrival" is core to the pitch
		'payment'             => 'woo',
		'conflict_check'      => false,
		'require_phone'       => false,
		'notify_email'        => '',
	],

	'post_types' => [
		// Matches the site's own language: the booking widget's field is
		// literally labelled "Destination", and the fleet section is "Fleet".
		'ks_place'   => [ 'singular' => 'Destination', 'plural' => 'Destinations', 'icon' => 'palmtree', 'taxonomy' => 'ks_region', 'rewrite' => 'destinations' ],
		'ks_vehicle' => [ 'singular' => 'Vehicle',      'plural' => 'Fleet',        'icon' => 'car',                                 'rewrite' => 'fleet' ],
	],

	'locale' => [
		'multilingual' => false,
		'languages'    => [ 'en' ],
	],
];
