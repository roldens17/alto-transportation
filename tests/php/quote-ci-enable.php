<?php
/*
Plugin Name: KlickSpark Quote CI Enable
Description: Enables quote module for disposable integration tests only.
*/
add_filter( 'ks/config', static function ( $config ) {
	$config['modules']['quotes'] = true;
	$config['quotes']['private_upload_dir'] = sys_get_temp_dir() . '/klickspark-quote-ci-private';
	return $config;
} );
