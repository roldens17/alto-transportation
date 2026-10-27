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
		add_action( 'admin_post_ks_quote_photo', [ __CLASS__, 'download_photo' ] );
		add_action( 'before_delete_post', [ __CLASS__, 'delete_private_photos' ] );
		add_filter( 'manage_ks_quote_posts_columns', [ __CLASS__, 'columns' ] );
		add_action( 'manage_ks_quote_posts_custom_column', [ __CLASS__, 'column_value' ], 10, 2 );
	}
	public static function register_type(): void {
		register_post_type( 'ks_quote', [
			'labels' => [ 'name' => 'Quote requests', 'singular_name' => 'Quote request' ],
			'public' => false, 'show_ui' => true, 'show_in_rest' => false,
			'capability_type' => 'post',
			'menu_icon' => 'dashicons-clipboard',
			'capabilities' => [ 'create_posts' => 'do_not_allow', 'edit_posts' => 'manage_options', 'edit_others_posts' => 'manage_options', 'edit_private_posts' => 'manage_options', 'read_private_posts' => 'manage_options', 'delete_posts' => 'manage_options', 'delete_others_posts' => 'manage_options', 'delete_private_posts' => 'manage_options', 'publish_posts' => 'manage_options' ],
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
			. '<label>Service <select name="service" required><option value="">Choose a service</option><option value="Standard Cleaning">Standard Cleaning</option><option value="Deep Cleaning">Deep Cleaning</option><option value="Move-In / Move-Out">Move-In / Move-Out</option><option value="Other Cleaning">Other Cleaning</option></select></label>'
			. '<label>Describe the job <textarea name="description" required maxlength="5000"></textarea></label>'
			. '<label>Project photos (up to 3 JPEG, PNG or WebP images, 5 MB each) <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple></label>'
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
		foreach ( (array) get_post_meta( $post->ID, '_ks_quote_photos', true ) as $index => $photo ) {
			$url = wp_nonce_url( add_query_arg( [ 'action' => 'ks_quote_photo', 'quote_id' => $post->ID, 'index' => $index ], admin_url( 'admin-post.php' ) ), 'ks_quote_photo_' . $post->ID );
			echo '<p><a href="' . esc_url( $url ) . '">View project photo ' . esc_html( $index + 1 ) . '</a></p>';
		}
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

	private static function private_dir(): string {
		// Private storage MUST be outside the publicly served uploads directory.
		// Configure an absolute path outside the document root for each deployment.
		return (string) ks_config( 'quotes.private_upload_dir', '' );
	}
	private static function validate_photos( array $files ) {
		if ( empty( $files['photos'] ) ) {
			return [];
		}
		$input = $files['photos'];
		$names = (array) ( $input['name'] ?? [] );
		if ( count( $names ) > 3 ) {
			return new WP_Error( 'ks_photo_limit', 'Upload up to 3 images.', [ 'status' => 400 ] );
		}
		$photos = [];
		foreach ( $names as $i => $name ) {
			$error = (int) ( $input['error'][$i] ?? UPLOAD_ERR_NO_FILE );
			if ( UPLOAD_ERR_NO_FILE === $error ) {
				continue;
			}
			if ( UPLOAD_ERR_OK !== $error || count( $photos ) >= 3 ) {
				return new WP_Error( 'ks_photo_limit', 'Upload up to 3 images, maximum 5 MB each.', [ 'status' => 400 ] );
			}
			$tmp = $input['tmp_name'][$i] ?? '';
			$size = (int) ( $input['size'][$i] ?? 0 );
			if ( ! is_uploaded_file( $tmp ) || $size < 1 || $size > 5 * MB_IN_BYTES ) {
				return new WP_Error( 'ks_photo_size', 'Invalid image upload or file too large.', [ 'status' => 400 ] );
			}
			$mime = ( new finfo( FILEINFO_MIME_TYPE ) )->file( $tmp );
			if ( ! in_array( $mime, [ 'image/jpeg', 'image/png', 'image/webp' ], true ) || ! @getimagesize( $tmp ) ) {
				return new WP_Error( 'ks_photo_type', 'Only valid JPEG, PNG and WebP images are accepted.', [ 'status' => 400 ] );
			}
			$photos[] = [ 'tmp' => $tmp, 'mime' => $mime ];
		}
		return $photos;
	}
	private static function store_photos( int $id, array $photos ) {
		$base = self::private_dir();
		$root = realpath( ABSPATH );
		if ( ! $base || ! is_dir( $base ) || ! is_writable( $base ) ) {
			return new WP_Error( 'ks_photo_storage', 'Private photo storage is not configured.', [ 'status' => 503 ] );
		}
		$base = realpath( $base );
		$uploads = wp_get_upload_dir();
		$public_uploads = realpath( $uploads['basedir'] ?? '' );
		$document_root = realpath( $_SERVER['DOCUMENT_ROOT'] ?? '' );
		if ( ! $base || ( $root && ( $base === $root || str_starts_with( $base, $root . DIRECTORY_SEPARATOR ) ) ) || ( $document_root && ( $base === $document_root || str_starts_with( $base, $document_root . DIRECTORY_SEPARATOR ) ) ) || ( $public_uploads && ( $base === $public_uploads || str_starts_with( $base, $public_uploads . DIRECTORY_SEPARATOR ) ) ) ) {
			return new WP_Error( 'ks_photo_storage', 'Private photo storage must be outside the WordPress directory.', [ 'status' => 503 ] );
		}
		$dir = $base . '/quote-' . $id . '-' . wp_generate_password( 16, false, false );
		if ( ! wp_mkdir_p( $dir ) ) {
			return new WP_Error( 'ks_photo_storage', 'Unable to create private storage.', [ 'status' => 503 ] );
		}
		// Restrict directory traversal by other local users where filesystem permissions allow.
		@chmod( $dir, 0700 );
		$saved = [];
		foreach ( $photos as $photo ) {
			$extension = [ 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp' ][ $photo['mime'] ];
			$filename = wp_generate_uuid4() . '.' . $extension;
			$path = $dir . '/' . $filename;
			if ( ! move_uploaded_file( $photo['tmp'], $path ) ) {
				foreach ( $saved as $item ) { @unlink( $item['path'] ); }
				@rmdir( $dir );
				return new WP_Error( 'ks_photo_storage', 'Unable to store project photos.', [ 'status' => 503 ] );
			}
			@chmod( $path, 0600 );
			$saved[] = [ 'path' => $path, 'mime' => $photo['mime'] ];
		}
		return $saved;
	}
	/** Remove private files when a quote is permanently deleted (not trashed). */
	public static function delete_private_photos( int $id ): void {
		if ( 'ks_quote' !== get_post_type( $id ) ) {
			return;
		}
		$base = realpath( self::private_dir() );
		if ( ! $base || ! is_dir( $base ) ) {
			return;
		}
		$directories = [];
		foreach ( (array) get_post_meta( $id, '_ks_quote_photos', true ) as $photo ) {
			if ( ! is_array( $photo ) ) {
				continue;
			}
			$path = realpath( $photo['path'] ?? '' );
			if ( ! $path || ! is_file( $path ) || ! str_starts_with( $path, $base . DIRECTORY_SEPARATOR ) ) {
				continue;
			}
			// Only remove files inside this quote's dedicated directory.
			$dir = dirname( $path );
			if ( ! preg_match( '/^quote-' . preg_quote( (string) $id, '/' ) . '-[a-zA-Z0-9]+$/', basename( $dir ) ) || dirname( $dir ) !== $base ) {
				continue;
			}
			if ( @unlink( $path ) ) {
				$directories[ $dir ] = true;
			}
		}
		foreach ( array_keys( $directories ) as $dir ) {
			@rmdir( $dir ); // Only removes empty quote directories.
		}
	}

	public static function download_photo(): void {
		$id = absint( $_GET['quote_id'] ?? 0 );
		$index = absint( $_GET['index'] ?? 0 );
		if ( ! current_user_can( 'manage_options' ) || 'ks_quote' !== get_post_type( $id ) ) {
			wp_die( 'Not authorized.', '', [ 'response' => 403 ] );
		}
		check_admin_referer( 'ks_quote_photo_' . $id );
		$photos = (array) get_post_meta( $id, '_ks_quote_photos', true );
		$photo = $photos[ $index ] ?? null;
		if ( ! is_array( $photo ) ) {
			wp_die( 'Photo not found.', '', [ 'response' => 404 ] );
		}
		$base = realpath( self::private_dir() );
		$path = $photo ? realpath( $photo['path'] ?? '' ) : false;
		$dir = $path ? dirname( $path ) : '';
		$quote_directory = $dir && preg_match( '/^quote-' . preg_quote( (string) $id, '/' ) . '-[a-zA-Z0-9]+$/', basename( $dir ) ) && dirname( $dir ) === $base;
		if ( ! $base || ! $path || ! is_file( $path ) || ! $quote_directory ) {
			wp_die( 'Photo not found.', '', [ 'response' => 404 ] );
		}
		$extensions = [ 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp' ];
		$mime = is_array( $photo ) ? (string) ( $photo['mime'] ?? '' ) : '';
		if ( ! isset( $extensions[ $mime ] ) ) {
			wp_die( 'Photo not found.', '', [ 'response' => 404 ] );
		}
		nocache_headers();
		header( 'Content-Type: ' . $mime );
		header( 'Content-Disposition: attachment; filename="project-photo-' . $index . '.' . $extensions[ $mime ] . '"' );
		header( 'X-Content-Type-Options: nosniff' );
		readfile( $path );
		exit;
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
		$photos = self::validate_photos( $request->get_file_params() );
		if ( is_wp_error( $photos ) ) {
			return $photos;
		}
		$id = wp_insert_post( [ 'post_type' => 'ks_quote', 'post_status' => 'private', 'post_title' => $name . ' — ' . $service ], true );
		if ( is_wp_error( $id ) || ! $id ) {
			return new WP_Error( 'ks_save', 'Unable to save your request. Please try again.', [ 'status' => 503 ] );
		}
		if ( $photos ) {
			$stored = self::store_photos( $id, $photos );
			if ( is_wp_error( $stored ) ) {
				wp_delete_post( $id, true );
				return $stored;
			}
			if ( ! update_post_meta( $id, '_ks_quote_photos', $stored ) ) {
				// A database write failure must not strand private uploads on disk.
				foreach ( $stored as $item ) {
					@unlink( $item['path'] );
				}
				if ( $stored ) {
					@rmdir( dirname( $stored[0]['path'] ) );
				}
				wp_delete_post( $id, true );
				return new WP_Error( 'ks_save', 'Unable to save your request. Please try again.', [ 'status' => 503 ] );
			}
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
KS_Quotes::init();
