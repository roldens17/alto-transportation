<?php
/** Disposable WP-CLI booking fixtures only; never install on a client site. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || '1' !== getenv( 'KS_BOOKING_CI' ) ) {
	return;
}
add_filter( 'ks/config', static function ( array $config ): array {
	$config['modules']['booking'] = '1' !== getenv( 'KS_BOOKING_CI_DISABLED' );
	$config['booking']['mode'] = getenv( 'KS_BOOKING_CI_MODE' ) ?: 'slot';
	$config['booking']['payment'] = getenv( 'KS_BOOKING_CI_PAYMENT' ) ?: 'none';
	$config['booking']['min_lead_hours'] = 0;
	$config['booking']['max_days_ahead'] = 60;
	$config['booking']['time_step_minutes'] = 30;
	$config['booking']['allow_stops'] = true;
	$config['booking']['stop_fee'] = 15;
	$config['booking']['notify_email'] = getenv( 'KS_BOOKING_CI_NOTIFY' ) ?: '';
	return $config;
} );
// CI has no licensed ACF Pro. Exercise the actual calendar and database with
// equivalent fixture values, without replacing any booking implementation.
if ( ! function_exists( 'get_field' ) ) {
	function get_field( $name, $post_id = false ) {
		return get_post_meta( (int) $post_id, $name, true );
	}
}
