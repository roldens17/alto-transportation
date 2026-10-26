<?php
// Executed by WP-CLI inside disposable WordPress CI.
// Enable module before theme bootstrap using a must-use plugin filter.
if ( ! class_exists( 'KS_Quotes' ) ) {
	fwrite( STDERR, 'Quote module missing; template=' . get_template() . '; stylesheet=' . get_stylesheet() . '; parent_exists=' . ( file_exists( get_template_directory() . '/inc/quotes.php' ) ? 'yes' : 'no' ) . '; module=' . ( ks_module_enabled( 'quotes' ) ? 'enabled' : 'disabled' ) . "\n" ); exit( 1 );
}
KS_Quotes::register_type();
if ( ! post_type_exists( 'ks_quote' ) ) {
	fwrite( STDERR, "Quote CPT missing\n" ); exit( 1 );
}
$req = new WP_REST_Request( 'POST', '/ks/v1/quote-request' );
$req->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
$req->set_body_params( [
	'name' => 'Test Customer',
	'email' => 'customer@example.test',
	'service' => 'Cleaning',
	'description' => 'Clean the office.',
	'consent' => '1',
] );
$invalid = clone $req;
$invalid->set_body_params( [ 'name' => '', 'email' => 'not-an-email', 'service' => '', 'description' => '' ] );
if ( ! is_wp_error( KS_Quotes::submit( $invalid ) ) ) {
	fwrite( STDERR, "Invalid input accepted\n" ); exit( 1 );
}
$response = KS_Quotes::submit( $req );
if ( is_wp_error( $response ) || 201 !== $response->get_status() ) {
	fwrite( STDERR, "Valid quote rejected: " . ( is_wp_error( $response ) ? $response->get_error_message() : $response->get_status() ) . "\n" ); exit( 1 );
}
$quotes = get_posts( [ 'post_type' => 'ks_quote', 'post_status' => 'private', 'numberposts' => 1 ] );
if ( 1 !== count( $quotes ) || 'new' !== get_post_meta( $quotes[0]->ID, '_ks_quote_status', true ) ) {
	fwrite( STDERR, "Private quote not saved\n" ); exit( 1 );
}
$files = [ 'photos' => [ 'name' => [ 'bad.exe' ], 'error' => [ UPLOAD_ERR_OK ], 'size' => [ 4 ], 'tmp_name' => [ '/tmp/missing' ] ] ];
$bad = clone $req;
$bad->set_file_params( $files );
if ( ! is_wp_error( KS_Quotes::submit( $bad ) ) ) {
	fwrite( STDERR, "Invalid upload accepted\n" ); exit( 1 );
}
echo "Quote intake validation and private record smoke passed\n";
