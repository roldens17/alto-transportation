<?php
/** Field-reader compatibility: native schedules remain native even if ACF is installed later. */
define( 'ABSPATH', __DIR__ );
function get_post_meta( $id, $name, $single ) {
	return [ 1 => [ 'duration_minutes' => 60, '_ks_booking_storage' => 'native' ], 2 => [ 'duration_minutes' => 30 ] ][$id][$name] ?? '';
}
function get_field( $name, $id ) { return 'acf-' . $id . '-' . $name; }
require __DIR__ . '/../../wordpress/klickspark-base/inc/booking/engine.php';
if ( 60 !== ks_booking_field( 'duration_minutes', 1 ) ) { throw new RuntimeException( 'Native schedule incorrectly passed through ACF' ); }
if ( 'acf-2-duration_minutes' !== ks_booking_field( 'duration_minutes', 2 ) ) { throw new RuntimeException( 'ACF-authored fields bypassed ACF' ); }
echo "Native and ACF field compatibility passed\n";
