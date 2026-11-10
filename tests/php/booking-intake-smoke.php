<?php
/** Real WordPress/MariaDB integration, without WooCommerce. Disposable CI only. */
defined( 'ABSPATH' ) || exit;
function ks_booking_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}
ks_booking_assert( ! class_exists( 'WooCommerce' ) && ! function_exists( 'wc_price' ), 'Run this regression without WooCommerce' );
ks_booking_assert( ! function_exists( 'get_field' ), 'Run this regression against native fields without an ACF shim' );
ks_booking_assert( ks_module_enabled( 'booking' ) && 'none' === ks_config( 'booking.payment' ), 'No-payment config missing' );
$routes = rest_get_server()->get_routes();
ks_booking_assert( isset( $routes['/ks/v1/consultation'] ), 'Intake route missing' );
ks_booking_assert( ! isset( $routes['/ks/v1/appointment'] ) && ! isset( $routes['/ks/v1/booking'] ), 'Paid route exposed on no-payment site' );
ks_booking_assert( ! isset( $routes['/wp/v2/ks_enquiry'] ), 'Enquiries exposed through REST' );
KS_Slot_Store::maybe_install();
$original_user = get_current_user_id();
$original_email = get_option( 'admin_email' );
$posts = [];
$users = [];
$mail = [];
$capture_mail = static function ( $result, $atts ) use ( &$mail ) {
	$mail[] = $atts;
	return true;
};
add_filter( 'pre_wp_mail', $capture_mail, 10, 2 );
$make_post = static function ( string $type, string $status = 'publish' ) use ( &$posts ): int {
	$id = wp_insert_post( [ 'post_type' => $type, 'post_status' => $status, 'post_title' => 'CI booking fixture' ], true );
	ks_booking_assert( ! is_wp_error( $id ) && $id > 0, 'Could not create booking fixture' );
	$posts[] = $id;
	return $id;
};
try {
	update_option( 'admin_email', 'booking-admin@example.test' );
	wp_set_current_user( 0 );
	$service = $make_post( 'ks_service' );
	$crew = $make_post( 'ks_practitioner' );
	update_post_meta( $service, 'duration_minutes', 60 );
	update_post_meta( $service, 'buffer_minutes', 30 );
	update_post_meta( $service, 'price', 123 );
	update_post_meta( $crew, 'services', [ $service ] );
	update_post_meta( $crew, 'slot_interval', 30 );
	$hours = array_map( static fn( $day ) => [ 'day' => $day, 'start_time' => '09:00', 'end_time' => '17:00' ], [ 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun' ] );
	update_post_meta( $crew, 'working_hours', $hours );
	$day = ( new DateTimeImmutable( '+7 days', wp_timezone() ) )->setTime( 0, 0 );
	$base = [
		'service' => $service, 'practitioner' => $crew,
		'date' => $day->format( 'Y-m-d' ), 'time' => '10:00',
		'name' => 'Booking Test', 'email' => 'booking-customer@example.test', 'consent' => true,
	];
	$submit = static function ( array $overrides = [], bool $valid_nonce = true ) use ( $base ) {
		$request = new WP_REST_Request( 'POST', '/ks/v1/consultation' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_header( 'X-WP-Nonce', $valid_nonce ? wp_create_nonce( 'wp_rest' ) : 'invalid' );
		$request->set_body( wp_json_encode( array_replace( $base, $overrides ) ) );
		return rest_do_request( $request );
	};
	$reject = static function ( array $overrides, string $message ) use ( $submit ): void {
		global $wpdb;
		$before = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . KS_Slot_Store::table() );
		$result = $submit( $overrides );
		ks_booking_assert( empty( $result->get_data()['ok'] ), $message );
		ks_booking_assert( $before === (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . KS_Slot_Store::table() ), 'Rejected request created a reservation' );
	};
	ks_booking_assert( 403 === $submit( [], false )->get_status(), 'Invalid nonce accepted' );
	$reject( [ 'time' => '03:00' ], 'Accepted time outside working hours' );
	$reject( [ 'time' => '10:15' ], 'Accepted start outside slot interval' );
	$reject( [ 'time' => '10:00:01' ], 'Accepted seconds outside offered start' );
	$reject( [ 'service' => $crew ], 'Accepted wrong service post type' );
	$reject( [ 'practitioner' => $service ], 'Accepted wrong crew post type' );
	wp_update_post( [ 'ID' => $service, 'post_status' => 'draft' ] );
	$reject( [], 'Accepted draft service' );
	wp_update_post( [ 'ID' => $service, 'post_status' => 'publish' ] );
	wp_update_post( [ 'ID' => $crew, 'post_status' => 'draft' ] );
	$reject( [], 'Accepted draft crew' );
	wp_update_post( [ 'ID' => $crew, 'post_status' => 'publish' ] );
	update_post_meta( $crew, 'services', [] );
	$reject( [], 'Accepted crew not assigned to service' );
	update_post_meta( $crew, 'services', [ $service ] );
	update_post_meta( $service, 'duration_minutes', 0 );
	$reject( [], 'Accepted service with zero duration' );
	update_post_meta( $service, 'duration_minutes', 60 );
	$off = $make_post( 'ks_timeoff' );
	update_post_meta( $off, 'practitioner', $crew );
	update_post_meta( $off, 'start_datetime', $day->setTime( 9, 0 )->format( 'Y-m-d H:i:s' ) );
	update_post_meta( $off, 'end_datetime', $day->setTime( 12, 0 )->format( 'Y-m-d H:i:s' ) );
	$reject( [], 'Accepted crew time off' );
	wp_delete_post( $off, true );
	// Failed pending transitions must not strand contact records or reservations.
	global $wpdb;
	$before_enquiries = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'ks_enquiry'" );
	$before_slots = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . KS_Slot_Store::table() );
	$injected = false;
	$fail_pending = static function ( string $sql ) use ( &$injected ): string {
		if ( str_starts_with( $sql, 'UPDATE' ) && str_contains( $sql, KS_Slot_Store::table() ) && str_contains( $sql, "`status` = 'pending'" ) ) {
			$injected = true;
			return 'SELECT 1 WHERE 1 = 0';
		}
		return $sql;
	};
	add_filter( 'query', $fail_pending );
	try {
		$failed = $submit( [ 'time' => '13:00' ] );
	} finally {
		remove_filter( 'query', $fail_pending );
	}
	ks_booking_assert( $injected && 503 === $failed->get_status(), 'Pending transition failure not reported' );
	ks_booking_assert( $before_enquiries === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'ks_enquiry'" ), 'Failed request stranded an enquiry' );
	ks_booking_assert( $before_slots === (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . KS_Slot_Store::table() ), 'Failed request stranded a hold' );
	$rendered = do_blocks( '<!-- wp:ks/appointment-picker /-->' );
	ks_booking_assert( str_contains( $rendered, 'USD 123.00' ), 'Priced no-payment picker failed without WooCommerce' );
	ob_start();
	do_action( 'admin_notices' );
	$notices = ob_get_clean();
	ks_booking_assert( ! str_contains( $notices, 'WooCommerce' ) && ! str_contains( $notices, 'container product' ), 'No-payment site requires payment dependencies' );
	// Ignore fixture-setup notifications (WordPress emails when admin_email changes).
	$mail = [];
	$accepted = $submit();
	ks_booking_assert( ! empty( $accepted->get_data()['ok'] ) && ! empty( $accepted->get_data()['pending'] ), 'Valid request not accepted for review' );
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . KS_Slot_Store::table() . ' WHERE practitioner_id = %d', $crew ) );
	ks_booking_assert( $row && 'pending' === $row->status, 'Request not pending' );
	ks_booking_assert( 90 * 60 === strtotime( $row->end_utc . ' UTC' ) - strtotime( $row->start_utc . ' UTC' ), 'Turnaround buffer was not stored' );
	$posts[] = (int) $row->enquiry_id;
	ks_booking_assert( 'private' === get_post_status( $row->enquiry_id ), 'New enquiry is not private' );
	$reject( [ 'time' => '11:00' ], 'Accepted appointment during existing turnaround buffer' );
	$expected_recipient = sanitize_email( (string) ks_config( 'booking.notify_email' ) );
	if ( ! is_email( $expected_recipient ) ) { $expected_recipient = 'booking-admin@example.test'; }
	ks_booking_assert( 2 === count( $mail ) && $expected_recipient === $mail[1]['to'], 'Business notification recipient incorrect: ' . wp_json_encode( array_column( $mail, 'to' ) ) );
	ks_booking_assert( 'booking-customer@example.test' === $mail[0]['to'], 'Customer notification missing' );
	// Legacy published records must receive the same protections without rewriting customer data.
	$legacy = $make_post( 'ks_enquiry' );
	foreach ( [ 'administrator', 'editor', 'author', 'subscriber' ] as $role ) {
		$id = wp_insert_user( [ 'user_login' => 'booking-ci-' . $role . '-' . wp_generate_password( 8, false ), 'user_pass' => wp_generate_password(), 'role' => $role ] );
		ks_booking_assert( ! is_wp_error( $id ), 'Could not create permission test user' );
		$users[] = $id;
		wp_set_current_user( $id );
		$type = get_post_type_object( 'ks_enquiry' );
		ks_booking_assert( ( 'administrator' === $role ) === current_user_can( $type->cap->edit_posts ), 'Enquiry listing permission incorrect for ' . $role );
		foreach ( [ (int) $row->enquiry_id, $legacy ] as $enquiry ) {
			foreach ( [ 'read_post', 'edit_post', 'delete_post' ] as $cap ) {
				ks_booking_assert( ( 'administrator' === $role ) === current_user_can( $cap, $enquiry ), $role . ' has incorrect ' . $cap . ' access' );
			}
		}
	}
	wp_set_current_user( 0 );
	foreach ( [ 'read_post', 'edit_post', 'delete_post' ] as $cap ) {
		ks_booking_assert( ! current_user_can( $cap, $row->enquiry_id ), 'Anonymous enquiry access allowed' );
	}
	echo "No-payment booking validation, buffers, privacy, rendering and notifications passed\n";
} finally {
	wp_set_current_user( $original_user );
	update_option( 'admin_email', $original_email );
	remove_filter( 'pre_wp_mail', $capture_mail, 10 );
	if ( isset( $crew ) ) {
		global $wpdb;
		$wpdb->delete( KS_Slot_Store::table(), [ 'practitioner_id' => $crew ] );
	}
	foreach ( array_unique( $posts ) as $id ) { wp_delete_post( $id, true ); }
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $users as $id ) { wp_delete_user( $id ); }
}
