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
// Reject submissions without a valid REST nonce, before any record is created.
$missing_nonce = clone $req;
$missing_nonce->set_header( 'X-WP-Nonce', 'invalid-nonce' );
$nonce_result = KS_Quotes::submit( $missing_nonce );
if ( ! is_wp_error( $nonce_result ) || 'ks_nonce' !== $nonce_result->get_error_code() ) {
	fwrite( STDERR, "Invalid REST nonce accepted\\n" ); exit( 1 );
}
// A valid upload shape with a PHP upload error must not create a quote.
$upload_failure = clone $req;
$upload_failure->set_file_params( [ 'photos' => [
	'name' => [ 'photo.jpg' ], 'error' => [ UPLOAD_ERR_PARTIAL ],
	'size' => [ 100 ], 'tmp_name' => [ '/tmp/missing' ],
] ] );
$upload_result = KS_Quotes::submit( $upload_failure );
if ( ! is_wp_error( $upload_result ) || 'ks_photo_limit' !== $upload_result->get_error_code() ) {
	fwrite( STDERR, "Partial upload accepted\\n" ); exit( 1 );
}
// Verify permanent deletion removes only this quote's private files.
$private_root = ks_config( 'quotes.private_upload_dir' );
if ( ! wp_mkdir_p( $private_root ) ) {
	fwrite( STDERR, "Cannot create isolated private test directory\\n" ); exit( 1 );
}
$quote_id = $quotes[0]->ID;
$quote_dir = $private_root . '/quote-' . $quote_id . '-testtoken';
if ( ! wp_mkdir_p( $quote_dir ) ) {
	fwrite( STDERR, "Cannot create quote test directory\\n" ); exit( 1 );
}
$photo_path = $quote_dir . '/photo.jpg';
$other_path = $private_root . '/unrelated.jpg';
file_put_contents( $photo_path, 'private-test-photo' );
file_put_contents( $other_path, 'unrelated-file-must-survive' );
update_post_meta( $quote_id, '_ks_quote_photos', [
	[ 'path' => $photo_path, 'mime' => 'image/jpeg' ],
	[ 'path' => $other_path, 'mime' => 'image/jpeg' ],
] );
wp_delete_post( $quote_id, true );
if ( file_exists( $photo_path ) || is_dir( $quote_dir ) || ! is_file( $other_path ) ) {
	fwrite( STDERR, "Private quote photo cleanup failed or deleted unrelated file\\n" ); exit( 1 );
}
unlink( $other_path );
rmdir( $private_root );
echo "Quote intake, private record and photo cleanup smoke passed\\n";
