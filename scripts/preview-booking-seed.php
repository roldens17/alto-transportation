<?php
/** Explicit, loopback-only demo seed. Never call from production provisioning. */
if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit( 1 ); }
$host = wp_parse_url( home_url(), PHP_URL_HOST );
if ( '1' !== getenv( 'KS_PREVIEW_BOOKING_DEMO' ) || ! in_array( $host, [ 'localhost', '127.0.0.1', '::1' ], true )
	|| 'fabulous-cleaning' !== get_stylesheet() || ! ks_module_enabled( 'booking' )
	|| 'slot' !== ks_config( 'booking.mode' ) || 'none' !== ks_config( 'booking.payment' ) || ks_module_enabled( 'quotes' ) ) {
	WP_CLI::error( 'Booking demo requires explicit opt-in, a loopback Fabulous Cleaning preview, and quotes/payments off.' );
}
$page = get_page_by_path( 'booking', OBJECT, 'page' );
$original = '<!-- wp:paragraph --><p>Online booking is not available yet. Please check back later.</p><!-- /wp:paragraph -->';
if ( $page && ! get_post_meta( $page->ID, '_ks_preview_booking_demo', true )
	&& ( 'draft' !== $page->post_status || $original !== $page->post_content ) ) {
	WP_CLI::error( 'Existing booking page is not an untouched draft seed; refusing to replace it.' );
}
$create = static function ( string $type, string $slug, string $title, array $meta ): int {
	$found = get_posts( [ 'post_type' => $type, 'post_status' => 'any', 'meta_key' => '_ks_preview_booking_demo', 'meta_value' => $slug, 'fields' => 'ids', 'numberposts' => 1 ] );
	if ( $found ) { return (int) $found[0]; }
	$id = wp_insert_post( [ 'post_type' => $type, 'post_status' => 'publish', 'post_name' => $slug, 'post_title' => $title,
		'meta_input' => array_merge( $meta, [ '_ks_preview_booking_demo' => $slug, '_ks_booking_storage' => 'native' ] ) ], true );
	if ( is_wp_error( $id ) || ! $id ) { WP_CLI::error( 'Could not create demo fixture.' ); }
	return $id;
};
$services = [];
foreach ( [ [ 'standard', 'Standard Cleaning', 60 ], [ 'deep', 'Deep Cleaning', 120 ], [ 'move', 'Move-In / Move-Out', 180 ] ] as [ $slug, $title, $duration ] ) {
	$services[] = $create( 'ks_service', 'demo-' . $slug, $title . ' (Demo)', [ 'duration_minutes' => $duration, 'buffer_minutes' => 30, 'price' => 0 ] );
}
$hours = array_map( static fn( $day ) => [ 'day' => $day, 'start_time' => '09:00', 'end_time' => '17:00' ], [ 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun' ] );
$create( 'ks_practitioner', 'demo-cleaning-team', 'Demo Cleaning Team', [ 'services' => $services, 'working_hours' => $hours, 'slot_interval' => 30, 'credentials' => 'Sample local availability' ] );
if ( ! $page || ! get_post_meta( $page->ID, '_ks_preview_booking_demo', true ) ) {
	$args = [ 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => 'booking', 'post_title' => 'Request a cleaning',
		'post_content' => '<!-- wp:paragraph {"className":"fc-demo-notice"} --><p class="fc-demo-notice">Local booking demo: these are sample services and times. Use test contact details; this does not arrange a real cleaning.</p><!-- /wp:paragraph -->',
		'meta_input' => [ '_ks_preview_booking_demo' => 'booking-page' ] ];
	if ( $page ) { $args['ID'] = $page->ID; }
	$id = wp_insert_post( $args, true );
	if ( is_wp_error( $id ) || ! $id ) { WP_CLI::error( 'Could not publish local booking demo.' ); }
}
update_option( 'permalink_structure', '/%postname%/' );
flush_rewrite_rules();
WP_CLI::success( 'Local booking demo is available at /booking/. Existing business services remain unchanged.' );
