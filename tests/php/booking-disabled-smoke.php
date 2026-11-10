<?php
/** Run in a fresh WP-CLI process so config/bootstrap state cannot leak between cases. */
defined( 'ABSPATH' ) || exit;
$routes = rest_get_server()->get_routes();
$enabled = ks_module_enabled( 'booking' );
$slot = 'slot' === ks_config( 'booking.mode' );
foreach ( [ '/booking', '/appointment', '/consultation' ] as $route ) {
	if ( isset( $routes[ '/ks/v1' . $route ] ) ) {
		throw new RuntimeException( 'Unexpected booking write endpoint: ' . $route );
	}
}
if ( ! $enabled ) {
	foreach ( [ '/availability', '/intake-questions', '/quote', '/places' ] as $route ) {
		if ( isset( $routes[ '/ks/v1' . $route ] ) ) {
			throw new RuntimeException( 'Disabled module exposed ' . $route );
		}
	}
	if ( class_exists( 'KS_Slot_Store' ) || post_type_exists( 'ks_enquiry' ) ) {
		throw new RuntimeException( 'Disabled module loaded storage or enquiry UI' );
	}
	$service = wp_insert_post( [ 'post_type' => 'ks_service', 'post_status' => 'publish', 'post_title' => 'Disabled booking fixture' ], true );
	if ( is_wp_error( $service ) || ! $service ) {
		throw new RuntimeException( 'Cannot create disabled-module fixture' );
	}
	try {
		foreach ( [ 'ks/appointment-picker', 'ks/booking-form' ] as $block ) {
			if ( '' !== trim( do_blocks( '<!-- wp:' . $block . ' /-->' ) ) ) {
				throw new RuntimeException( 'Disabled module rendered booking UI' );
			}
		}
	} finally {
		wp_delete_post( $service, true );
	}
} elseif ( $slot && ! isset( $routes['/ks/v1/availability'] ) ) {
	throw new RuntimeException( 'Enabled slot availability route missing' );
}
echo "Booking route isolation checks passed\n";
