<?php
/**
 * Block registration. Every block is a directory under /blocks with a
 * block.json, so adding one is dropping in a folder.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', function () {
	foreach ( glob( KS_BASE_DIR . '/blocks/*/block.json' ) as $manifest ) {
		register_block_type( dirname( $manifest ) );
	}
} );

/** Data the booking form needs on first paint, so it renders without a round trip. */
function ks_booking_bootstrap_data(): array {
	$cfg = ks_config( 'booking' );

	return [
		'restUrl'  => esc_url_raw( rest_url( 'ks/v1' ) ),
		'nonce'    => wp_create_nonce( 'wp_rest' ),
		'currency' => $cfg['currency'],
		'config'   => [
			'roundTrip'    => (bool) $cfg['round_trip'],
			'allowStops'   => (bool) $cfg['allow_stops'],
			'stopFee'      => (float) $cfg['stop_fee'],
			'minLeadHours' => (int) $cfg['min_lead_hours'],
			'timeStep'     => (int) $cfg['time_step_minutes'],
			'requireFlight'=> (bool) $cfg['require_flight_no'],
		],
	];
}
