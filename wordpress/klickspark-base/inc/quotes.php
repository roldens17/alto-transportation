<?php
/** First-party quote requests. Private by default; no payment dependency. */
defined( 'ABSPATH' ) || exit;

final class KS_Quotes {
	public static function init(): void {
		if ( ! ks_module_enabled( 'quotes' ) ) {
			return;
		}
		add_action( 'init', [ __CLASS__, 'register_type' ] );
		add_action( 'rest_api_init', [ __CLASS__, 'routes' ] );
		add_shortcode( 'klickspark_quote_form', [ __CLASS__, 'form' ] );
	}
	public static function register_type(): void {
		register_post_type( 'ks_quote', [
			'labels' => [ 'name' => 'Quote requests', 'singular_name' => 'Quote request' ],
			'public' => false, 'show_ui' => true, 'show_in_rest' => false,
			'menu_icon' => 'dashicons-clipboard',
			'capabilities' => [ 'create_posts' => 'do_not_allow' ],
			'map_meta_cap' => true, 'supports' => [ 'title' ],
		] );
	}
	public static function routes(): void {
		register_rest_route( 'ks/v1', '/quote-request', [
			'methods' => 'POST',
			'permission_callback' => '__return_true',
			'callback' => [ __CLASS__, 'submit' ],
		] );
	}
	public static function form(): string {
		$action = esc_url( rest_url( 'ks/v1/quote-request' ) );
		$nonce = esc_attr( wp_create_nonce( 'wp_rest' ) );
		return '<form class="ks-quote-form" method="post" enctype="multipart/form-data" action="' . $action . '" data-ks-rest-nonce="' . $nonce . '">'
			. '<label>Name <input name="name" required maxlength="120"></label>'
			. '<label>Email <input type="email" name="email" required maxlength="190"></label>'
			. '<label>Phone <input name="phone" maxlength="40"></label>'
			. '<label>Service <input name="service" required maxlength="120"></label>'
			. '<label>Describe the job <textarea name="description" required maxlength="5000"></textarea></label>'
			. '<label>Project photos (up to 3, JPEG, PNG or WebP; 5 MB each) <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple></label>'
			. '<label><input type="checkbox" name="consent" value="1" required> I agree to be contacted about this request.</label>'
			. '<button type="submit">Request a quote</button></form>';
	}
	public static function submit( WP_REST_Request $request ) {
		if ( ! wp_verify_nonce( $request->get_header( 'X-WP-Nonce' ), 'wp_rest' ) ) {
			return new WP_Error( 'ks_nonce', 'Please reload the form and try again.', [ 'status' => 403 ] );
		}
		$ip = (string) ( $_SERVER['REMOTE_ADDR'] ?? 'unknown' );
		$key = 'ks_quote_limit_' . md5( $ip );
		$count = (int) get_transient( $key );
		if ( $count >= 5 ) {
			return new WP_Error( 'ks_rate_limit', 'Too many requests. Please try again later.', [ 'status' => 429 ] );
		}
		$raw = $request->get_params();
		$name = sanitize_text_field( $raw['name'] ?? '' );
		$email = sanitize_email( $raw['email'] ?? '' );
		$phone = sanitize_text_field( $raw['phone'] ?? '' );
		$service = sanitize_text_field( $raw['service'] ?? '' );
		$description = sanitize_textarea_field( $raw['description'] ?? '' );
		if ( ! $name || strlen( $name ) > 120 || ! is_email( $email ) || ! $service || strlen( $service ) > 120 || ! $description || strlen( $description ) > 5000 || empty( $raw['consent'] ) ) {
			return new WP_Error( 'ks_validation', 'Please complete all required fields.', [ 'status' => 400 ] );
		}
		// Photos are deliberately deferred until private storage and authorized
		// download handling are available. Never put project photos in Media Library.
		$files = $request->get_file_params();
		if ( ! empty( $files ) ) {
			return new WP_Error( 'ks_photos_unavailable', 'Photo uploads are not enabled yet. Please submit without photos.', [ 'status' => 400 ] );
		}
		$id = wp_insert_post( [ 'post_type' => 'ks_quote', 'post_status' => 'private', 'post_title' => $name . ' — ' . $service ], true );
		if ( is_wp_error( $id ) || ! $id ) {
			return new WP_Error( 'ks_save', 'Unable to save your request. Please try again.', [ 'status' => 503 ] );
		}
		update_post_meta( $id, '_ks_quote_contact', [ 'name' => $name, 'email' => $email, 'phone' => $phone ] );
		update_post_meta( $id, '_ks_quote_service', $service );
		update_post_meta( $id, '_ks_quote_description', $description );
		update_post_meta( $id, '_ks_quote_status', 'new' );
		set_transient( $key, $count + 1, HOUR_IN_SECONDS );
		wp_mail( get_option( 'admin_email' ), 'New quote request — ' . get_bloginfo( 'name' ), 'A new quote request is ready for review in WordPress admin.' );
		return new WP_REST_Response( [ 'ok' => true, 'message' => 'Your quote request was received. We will contact you after reviewing it.' ], 201 );
	}
}
add_action( 'after_setup_theme', [ 'KS_Quotes', 'init' ] );
