<?php
/**
 * Config resolution.
 *
 * A child theme drops a config.php returning an array. Anything it omits
 * falls back to the defaults below. This file is the contract between the
 * parent theme and every client site — change it carefully.
 */

defined( 'ABSPATH' ) || exit;

function ks_config_defaults(): array {
	return [
		// Which modules to switch on for this client.
		'modules' => [
			'booking'    => true,
			'fleet'      => true,   // vehicles / bookable resources
			'places'     => true,   // origins + destinations
			'activities' => false,
		],

		/**
		 * Booking mode:
		 *  'route' — A to B with resource capacity (transfers, shuttles, courier)
		 *  'slot'  — date + time + duration against a resource (salon, clinic, studio)
		 *  'unit'  — quantity x nights or hours (rentals, venues)
		 */
		'booking' => [
			'mode'                => 'route',
			'currency'            => 'USD',
			'round_trip'          => true,
			'round_trip_modifier' => 1.9,   // 2.0 = no discount on the return leg
			'allow_stops'         => true,
			'stop_fee'            => 15.00,
			'min_lead_hours'      => 3,
			'max_days_ahead'      => 365,
			'time_step_minutes'   => 15,
			'product_id'          => 0,     // Woo container product, set per site
			'require_flight_no'   => true,

			/**
			 * How a booking is settled:
			 *  'woo'  — cart and checkout, paid up front
			 *  'none' — intake form, confirmed by email, no payment
			 */
			'payment'             => 'woo',
			'conflict_check'      => false, // hold in a review queue before confirming
			'require_phone'       => false,
			'notify_email'        => '',    // defaults to the admin email
		],

		/**
		 * Regulated-industry notices. Off unless a client needs them.
		 */
		'compliance' => [
			'enabled'          => false,
			'force_footer'     => true,
			'jurisdictions'    => [],
			'site_notice'      => '',
			'email_notice'     => '',
			'confirmed_notice' => '',
			'declined_notice'  => '',
		],

		// Post types to register. Labels are overridable per client.
		'post_types' => [
			'ks_place'    => [ 'singular' => 'Place',    'plural' => 'Places',     'icon' => 'location-alt', 'taxonomy' => 'ks_region' ],
			'ks_vehicle'  => [ 'singular' => 'Vehicle',  'plural' => 'Fleet',      'icon' => 'car' ],
			'ks_rate'     => [ 'singular' => 'Rate',     'plural' => 'Rates',      'icon' => 'money-alt', 'public' => false ],
			'ks_activity' => [ 'singular' => 'Activity', 'plural' => 'Activities', 'icon' => 'palmtree' ],
		],

		'locale' => [
			'multilingual' => false,
			'languages'    => [ 'en' ],
		],
	];
}

function ks_config( ?string $path = null, $fallback = null ) {
	static $config = null;

	if ( null === $config ) {
		$file   = get_stylesheet_directory() . '/config.php';
		$client = file_exists( $file ) ? require $file : [];
		$config = ks_array_merge_deep( ks_config_defaults(), is_array( $client ) ? $client : [] );

		/** Filter the resolved site config. */
		$config = apply_filters( 'ks/config', $config );
	}

	if ( null === $path ) {
		return $config;
	}

	$value = $config;
	foreach ( explode( '.', $path ) as $segment ) {
		if ( ! is_array( $value ) || ! array_key_exists( $segment, $value ) ) {
			return $fallback;
		}
		$value = $value[ $segment ];
	}

	return $value;
}

function ks_module_enabled( string $module ): bool {
	return (bool) ks_config( "modules.$module", false );
}

function ks_array_merge_deep( array $base, array $override ): array {
	foreach ( $override as $key => $value ) {
		if ( is_array( $value ) && isset( $base[ $key ] ) && is_array( $base[ $key ] ) && ! array_is_list( $value ) ) {
			$base[ $key ] = ks_array_merge_deep( $base[ $key ], $value );
		} else {
			$base[ $key ] = $value;
		}
	}
	return $base;
}
