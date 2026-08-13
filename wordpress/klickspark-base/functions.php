<?php
/**
 * KlickSpark Base — bootstrap.
 *
 * Everything is config-driven. A child theme provides config.php and the
 * parent registers the content model, booking engine and blocks from it.
 */

defined( 'ABSPATH' ) || exit;

define( 'KS_BASE_VERSION', '0.1.0' );
define( 'KS_BASE_DIR', __DIR__ );

require_once KS_BASE_DIR . '/inc/config.php';
require_once KS_BASE_DIR . '/inc/setup.php';
require_once KS_BASE_DIR . '/inc/cpt.php';
require_once KS_BASE_DIR . '/inc/acf.php';
require_once KS_BASE_DIR . '/inc/booking/engine.php';
require_once KS_BASE_DIR . '/inc/booking/woo.php';
require_once KS_BASE_DIR . '/inc/booking/rest.php';

// Scheduling mode loads only when the client uses it.
if ( 'slot' === ks_config( 'booking.mode' ) ) {
	require_once KS_BASE_DIR . '/inc/booking/mode-slot.php';

	// Payment gate: a cart, or an intake form with a review queue.
	if ( 'none' === ks_config( 'booking.payment', 'woo' ) ) {
		require_once KS_BASE_DIR . '/inc/booking/intake.php';
		require_once KS_BASE_DIR . '/inc/booking/review-queue.php';
	} else {
		require_once KS_BASE_DIR . '/inc/booking/woo-slot.php';
	}
}

require_once KS_BASE_DIR . '/inc/compliance.php';
require_once KS_BASE_DIR . '/inc/blocks.php';
