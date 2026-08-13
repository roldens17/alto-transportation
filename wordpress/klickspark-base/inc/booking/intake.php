<?php
/**
 * Consultation intake without a cart.
 *
 * Set booking.payment to 'none' and the flow becomes: pick a time, answer the
 * intake questions, slot goes into the review queue, both sides get an email.
 * No WooCommerce involved — which is the point of keeping the engine free of it.
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/store.php';

class KS_Intake {

	public static function init(): void {
		if ( 'none' !== ks_config( 'booking.payment', 'woo' ) ) {
			return;
		}

		add_action( 'init', [ self::class, 'register_enquiry' ] );
		add_action( 'rest_api_init', [ self::class, 'register_routes' ] );
		add_action( 'acf/init', [ self::class, 'register_fields' ] );
	}

	public static function register_enquiry(): void {
		register_post_type( 'ks_enquiry', [
			'labels'       => [ 'name' => 'Enquiries', 'singular_name' => 'Enquiry' ],
			'public'       => false,
			'show_ui'      => true,
			'show_in_rest' => false,
			'menu_icon'    => 'dashicons-format-status',
			'supports'     => [ 'title' ],
			'capabilities' => [ 'create_posts' => 'do_not_allow' ],
			'map_meta_cap' => true,
		] );
	}

	/**
	 * Intake questions are authored per matter type, so a family case and a
	 * removal case ask different things without a code change.
	 */
	public static function register_fields(): void {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		acf_add_local_field_group( [
			'key'      => 'group_ks_intake',
			'title'    => 'Intake questions',
			'location' => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'ks_service' ] ] ],
			'fields'   => [
				[
					'key' => 'field_ks_i_questions', 'label' => 'Questions', 'name' => 'intake_questions',
					'type' => 'repeater', 'layout' => 'row', 'button_label' => 'Add question',
					'instructions' => 'Asked before the consultation is confirmed. Keep it to what you need to check conflicts and prepare.',
					'sub_fields' => [
						[ 'key' => 'field_ks_i_label',    'label' => 'Question', 'name' => 'label', 'type' => 'text', 'required' => 1 ],
						[ 'key' => 'field_ks_i_type',     'label' => 'Answer type', 'name' => 'type', 'type' => 'select', 'choices' => [ 'text' => 'Short text', 'textarea' => 'Long text', 'select' => 'Choose one', 'date' => 'Date' ], 'default_value' => 'text' ],
						[ 'key' => 'field_ks_i_choices',  'label' => 'Choices', 'name' => 'choices', 'type' => 'textarea', 'instructions' => 'One per line. Only used for "Choose one".', 'conditional_logic' => [ [ [ 'field' => 'field_ks_i_type', 'operator' => '==', 'value' => 'select' ] ] ] ],
						[ 'key' => 'field_ks_i_required', 'label' => 'Required', 'name' => 'required', 'type' => 'true_false', 'ui' => 1, 'default_value' => 1 ],
					],
				],
			],
		] );
	}

	public static function register_routes(): void {
		register_rest_route( 'ks/v1', '/intake-questions', [
			'methods'             => 'GET',
			'permission_callback' => '__return_true',
			'args'                => [ 'service' => [ 'required' => true, 'sanitize_callback' => 'absint' ] ],
			'callback'            => function ( WP_REST_Request $request ) {
				return new WP_REST_Response( [ 'ok' => true, 'questions' => self::questions( $request->get_param( 'service' ) ) ], 200 );
			},
		] );

		register_rest_route( 'ks/v1', '/consultation', [
			'methods'             => 'POST',
			'permission_callback' => '__return_true',
			'callback'            => [ self::class, 'submit' ],
		] );
	}

	public static function questions( int $service ): array {
		$rows = get_field( 'intake_questions', $service ) ?: [];
		$out  = [];

		foreach ( $rows as $index => $row ) {
			$out[] = [
				'key'      => 'q' . $index,
				'label'    => (string) ( $row['label'] ?? '' ),
				'type'     => (string) ( $row['type'] ?? 'text' ),
				'required' => ! empty( $row['required'] ),
				'choices'  => array_values( array_filter( array_map( 'trim', explode( "\n", (string) ( $row['choices'] ?? '' ) ) ) ) ),
			];
		}

		return $out;
	}

	public static function submit( WP_REST_Request $request ) {
		if ( ! wp_verify_nonce( $request->get_header( 'X-WP-Nonce' ), 'wp_rest' ) ) {
			return self::fail( [ 'form' => 'Your session expired. Reload the page and try again.' ], 403 );
		}

		$raw     = (array) $request->get_json_params();
		$booking = KS_Booking_Request::from_array( $raw );

		$name  = sanitize_text_field( $raw['name'] ?? '' );
		$email = sanitize_email( $raw['email'] ?? '' );
		$phone = sanitize_text_field( $raw['phone'] ?? '' );

		$errors = [];

		if ( ! $booking->service ) {
			$errors['service'] = 'Choose what your case is about.';
		}
		if ( ! $booking->practitioner ) {
			$errors['practitioner'] = 'Choose who you would like to speak with.';
		}
		if ( ! $name ) {
			$errors['name'] = 'Add your full name.';
		}
		if ( ! is_email( $email ) ) {
			$errors['email'] = 'Add an email address we can reach you at.';
		}
		if ( ! empty( ks_config( 'booking.require_phone' ) ) && ! $phone ) {
			$errors['phone'] = 'Add a phone number.';
		}
		if ( empty( $raw['consent'] ) ) {
			$errors['consent'] = 'Please confirm you have read the notice below before submitting.';
		}

		$errors = array_merge( $errors, KS_Booking_Engine::validate( $booking ) );

		// Intake answers.
		$answers  = [];
		$supplied = (array) ( $raw['answers'] ?? [] );

		foreach ( self::questions( $booking->service ) as $question ) {
			$value = sanitize_textarea_field( (string) ( $supplied[ $question['key'] ] ?? '' ) );

			if ( $question['required'] && '' === $value ) {
				$errors[ $question['key'] ] = 'This one is needed: ' . $question['label'];
			}

			if ( '' !== $value ) {
				$answers[ $question['label'] ] = $value;
			}
		}

		if ( $errors ) {
			return self::fail( $errors );
		}

		$start    = $booking->departs_at();
		$duration = KS_Slot_Engine::duration( $booking->service );
		$hold     = KS_Slot_Store::hold( $booking->practitioner, $booking->service, $start, $duration, wp_generate_uuid4() );

		if ( is_wp_error( $hold ) ) {
			return self::fail( [ 'date' => $hold->get_error_message() ] );
		}

		$enquiry_id = wp_insert_post( [
			'post_type'   => 'ks_enquiry',
			'post_status' => 'publish',
			'post_title'  => sprintf( '%s — %s', $name, get_the_title( $booking->service ) ),
		] );

		if ( is_wp_error( $enquiry_id ) ) {
			KS_Slot_Store::decline( $hold );
			return self::fail( [ 'form' => 'We could not record that. Call the office and we will book you in.' ] );
		}

		update_post_meta( $enquiry_id, '_ks_contact', compact( 'name', 'email', 'phone' ) );
		update_post_meta( $enquiry_id, '_ks_answers', $answers );
		update_post_meta( $enquiry_id, '_ks_appointment_id', $hold );
		update_post_meta( $enquiry_id, '_ks_submitted_utc', gmdate( 'Y-m-d H:i:s' ) );

		global $wpdb;
		$wpdb->update( KS_Slot_Store::table(), [
			'enquiry_id'    => $enquiry_id,
			'patient_name'  => $name,
			'patient_email' => $email,
		], [ 'id' => $hold ] );

		// Conflict check gate: the time is reserved but nothing is confirmed yet.
		$needs_review = (bool) ks_config( 'booking.conflict_check', false );

		if ( $needs_review ) {
			KS_Slot_Store::mark_pending( $hold );
		} else {
			KS_Slot_Store::approve( $hold );
		}

		do_action( 'ks/intake/submitted', $enquiry_id, $hold, $needs_review );

		self::notify( $enquiry_id, $hold, $needs_review );

		return new WP_REST_Response( [
			'ok'      => true,
			'pending' => $needs_review,
			'message' => $needs_review
				? sprintf(
					'We have reserved %s and started a conflict check. You will get an email confirming within one business day. This is not yet an attorney-client relationship.',
					wp_date( 'l j F, g:ia', $start->getTimestamp() )
				)
				: sprintf( 'You are booked for %s. Check your email for the details.', wp_date( 'l j F, g:ia', $start->getTimestamp() ) ),
		], 200 );
	}

	private static function notify( int $enquiry_id, int $appointment_id, bool $pending ): void {
		$row     = KS_Slot_Store::get( $appointment_id );
		$contact = get_post_meta( $enquiry_id, '_ks_contact', true );
		$answers = get_post_meta( $enquiry_id, '_ks_answers', true ) ?: [];

		if ( ! $row || empty( $contact['email'] ) ) {
			return;
		}

		$start = ( new DateTimeImmutable( $row->start_utc, new DateTimeZone( 'UTC' ) ) )->setTimezone( wp_timezone() );
		$when  = wp_date( 'l j F Y, g:ia', $start->getTimestamp() );
		$who   = get_the_title( (int) $row->practitioner_id );
		$what  = get_the_title( (int) $row->service_id );

		// To the client.
		$lines = [
			$pending
				? "We have reserved this time while we run a conflict check:"
				: "Your consultation is confirmed:",
			'',
			"What:  $what",
			"With:  $who",
			"When:  $when",
			'',
		];

		if ( $pending ) {
			$lines[] = 'We will email again within one business day to confirm or to suggest another time.';
			$lines[] = '';
		}

		$lines[] = ks_config( 'compliance.email_notice', 'Sending this form does not create an attorney-client relationship. Do not send confidential or time-sensitive information until we have confirmed representation in writing.' );

		wp_mail(
			$contact['email'],
			sprintf( '%s — %s', $pending ? 'Time reserved' : 'Consultation confirmed', get_bloginfo( 'name' ) ),
			implode( "\n", $lines )
		);

		// To the firm.
		$internal = [ "New consultation request.", '', "Name:  {$contact['name']}", "Email: {$contact['email']}" ];

		if ( ! empty( $contact['phone'] ) ) {
			$internal[] = "Phone: {$contact['phone']}";
		}

		$internal[] = '';
		$internal[] = "What:  $what";
		$internal[] = "With:  $who";
		$internal[] = "When:  $when";
		$internal[] = '';

		foreach ( $answers as $question => $answer ) {
			$internal[] = "$question";
			$internal[] = "  $answer";
		}

		if ( $pending ) {
			$internal[] = '';
			$internal[] = 'Awaiting conflict check: ' . admin_url( 'admin.php?page=ks-review-queue' );
		}

		wp_mail(
			ks_config( 'booking.notify_email', get_option( 'admin_email' ) ),
			sprintf( '[%s] %s — %s', $pending ? 'Review' : 'Booked', $contact['name'], $what ),
			implode( "\n", $internal )
		);
	}

	private static function fail( array $errors, int $status = 200 ): WP_REST_Response {
		return new WP_REST_Response( [ 'ok' => false, 'errors' => $errors ], $status );
	}
}

add_action( 'after_setup_theme', [ 'KS_Intake', 'init' ] );
