<?php
/*
Plugin Name: KlickSpark Quote CI Enable
Description: Enables quote module for disposable integration tests only.
*/
add_filter( 'ks/config', static function ( $config ) {
	$config['modules']['quotes'] = true;
	$config['quotes']['private_upload_dir'] = '/var/ks-private';
	return $config;
} );

// CI-only failure injection: simulate a rejected metadata write for a specifically
// named HTTP quote, after its photo has been moved into private storage.
$ks_fail_photo_metadata = static function ( $check, $object_id, $meta_key ) {
	if ( '_ks_quote_photos' !== $meta_key || 'ks_quote' !== get_post_type( $object_id ) ) {
		return $check;
	}
	$post = get_post( $object_id );
	if ( $post && 'HTTP Metadata Failure — Cleaning' === $post->post_title ) {
		return false;
	}
	return $check;
};
add_filter( 'update_post_metadata', $ks_fail_photo_metadata, 10, 3 );
add_filter( 'add_post_metadata', $ks_fail_photo_metadata, 10, 3 );

 // CI-only: reject the second photo move after the first has been saved.
add_filter( 'ks_quote_allow_photo_move', static function ( $allow, $quote_id, $index ) {
  $post = get_post( $quote_id );
  if ( $post && 'HTTP Mid-Batch Failure — Cleaning' === $post->post_title && 1 === $index ) {
    return false;
  }
  return $allow;
}, 10, 3 );
