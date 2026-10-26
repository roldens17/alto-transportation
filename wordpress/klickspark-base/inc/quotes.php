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
		add_action( 'add_meta_boxes_ks_quote', [ __CLASS__, 'meta_boxes' ] );
		add_action( 'admin_post_ks_quote_review', [ __CLASS__, 'review' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'admin_scripts' ] );
		add_filter( 'manage_ks_quote_posts_columns', [ __CLASS__, 'columns' ] );
		add_action( 'manage_ks_quote_posts_custom_column', [ __CLASS__, 'column_value' ], 10, 2 );
	}
	public static function register_type(): void {
		register_post_type( 'ks_quote', [
			'labels' => [ 'name' => 'Quote requests', 'singular_name' => 'Quote request' ],
			'public' => false, 'show_ui' => true, 'show_in_rest' => false,
			'capability_type' => 'post', 'map_meta_cap' => true,
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
		wp_enqueue_script( 'ks-quote-form', get_template_directory_uri() . '/assets/js/quote-form.js', [], KS_BASE_VERSION, true );
		$action = esc_url( rest_url( 'ks/v1/quote-request' ) );
		$nonce = esc_attr( wp_create_nonce( 'wp_rest' ) );
		return '<form class="ks-quote-form" method="post" enctype="multipart/form-data" action="' . $action . '" data-ks-rest-nonce="' . $nonce . '">'
			. '<label>Name <input name="name" required maxlength="120"></label>'
			. '<label>Email <input type="email" name="email" required maxlength="190"></label>'
			. '<label>Phone <input name="phone" maxlength="40"></label>'
			. '<label>Service <input name="service" required maxlength="120"></label>'
			. '<label>Describe the job <textarea name="description" required maxlength="5000"></textarea></label>'
			
			. '<label><input type="checkbox" name="consent" value="1" required> I agree to be contacted about this request.</label>'
			 . '<button type="submit">Request a quote</button><p class="ks-quote-result" role="status" aria-live="polite"></p></form>';
	}

	public static function admin_scripts(): void {
		$screen = get_current_screen();
		if ( $screen && 'ks_quote' === $screen->post_type ) {
			wp_enqueue_script( 'ks-quote-admin', get_template_directory_uri() . '/assets/js/quote-admin.js', [], KS_BASE_VERSION, true );
		}
	}
	public static function meta_boxes(): void {
		add_meta_box( 'ks_quote_details', 'Request details', [ __CLASS__, 'details' ], 'ks_quote', 'normal', 'high' );
	}
	public static function details( WP_Post $post ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			echo '<p>Administrator access required.</p>';
			return;
		}
		$contact = (array) get_post_meta( $post->ID, '_ks_quote_contact', true );
		$service = (string) get_post_meta( $post->ID, '_ks_quote_service', true );
		$description = (string) get_post_meta( $post->ID, '_ks_quote_description', true );
		$status = (string) get_post_meta( $post->ID, '_ks_quote_status', true );
		echo '<p><strong>Status:</strong> ' . esc_html( $status ) . '</p>';
		foreach ( [ 'name' => 'Name', 'email' => 'Email', 'phone' => 'Phone' ] as $key => $label ) {
			echo '<p><strong>' . esc_html( $label ) . ':</strong> ' . esc_html( $contact[ $key ] ?? '' ) . '</p>';
		}
		echo '<p><strong>Service:</strong> ' . esc_html( $service ) . '</p>';
		echo '<p><strong>Description:</strong></p><p>' . nl2br( esc_html( $description ) ) . '</p>';
		echo '<p>Review actions: ';
		foreach ( [ 'reviewing' => 'Mark reviewing', 'closed' => 'Close request' ] as $decision => $label ) {
			$url = wp_nonce_url( add_query_arg( [ 'action' => 'ks_quote_review', 'quote_id' => $post->ID, 'decision' => $decision ], admin_url( 'admin-post.php' ) ), 'ks_quote_review_' . $post->ID );
			echo '<button type="button" class="button ks-quote-decision" style="margin-right:8px" data-action="' . esc_url( $url ) . '">' . esc_html( $label ) . '</button>';
		}
		echo '</p>';
	}
	public static function review(): void {
		$id = absint( $_POST['quote_id'] ?? 0 );
		if ( ! $id || ! current_user_can( 'manage_options' ) || 'ks_quote' !== get_post_type( $id ) ) {
			wp_die( 'Not allowed.', '', [ 'response' => 403 ] );
		}
		check_admin_referer( 'ks_quote_review_' . $id );
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			wp_die( 'POST required.', '', [ 'response' => 405 ] );
		}
		$decision = sanitize_key( $_POST['decision'] ?? '' );
		if ( ! in_array( $decision, [ 'reviewing', 'closed' ], true ) ) {
			wp_die( 'Invalid status.', '', [ 'response' => 400 ] );
		}
		update_post_meta( $id, '_ks_quote_status', $decision );
		wp_safe_redirect( get_edit_post_link( $id, 'raw' ) );
		exit;
	}
	public static function columns( array $columns ): array {
		$columns['ks_quote_status'] = 'Request status';
		return $columns;
	}
	public static function column_value( string $column, int $id ): void {
		if ( 'ks_quote_status' === $column ) {
			echo esc_html( get_post_meta( $id, '_ks_quote_status', true ) ?: 'new' );
		}
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
