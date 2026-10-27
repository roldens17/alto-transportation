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
add_filter( 'update_post_metadata', static function ( $check, $object_id, $meta_key ) {
	if ( '_ks_quote_photos' !== $meta_key || 'ks_quote' !== get_post_type( $object_id ) ) {
		return $check;
	}
	$post = get_post( $object_id );
	if ( $post && 'HTTP Metadata Failure — Cleaning' === $post->post_title ) {
		return false;
	}
	return $check;
}, 10, 3 );
