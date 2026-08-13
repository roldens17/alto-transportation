<?php
/**
 * REST endpoints the booking form talks to.
 *
 * Public routes, so they are read-only and rate-limited by nonce on write.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'rest_api_init', function () {
	if ( ! ks_module_enabled( 'booking' ) ) {
		return;
	}

	register_rest_route( 'ks/v1', '/places', [
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => function ( WP_REST_Request $request ) {
			$role = $request->get_param( 'role' ) === 'dropoff' ? 'is_dropoff' : 'is_pickup';

			$places = get_posts( [
				'post_type'      => 'ks_place',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'meta_query'     => [ [ 'key' => $role, 'value' => '1' ] ],
			] );

			return array_map( fn( $p ) => [
				'id'     => $p->ID,
				'label'  => get_the_title( $p ),
				'type'   => (string) get_field( 'place_type', $p->ID ),
				'region' => wp_get_post_terms( $p->ID, 'ks_region', [ 'fields' => 'names' ] ),
			], $places );
		},
	] );

	register_rest_route( 'ks/v1', '/quote', [
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => function ( WP_REST_Request $request ) {
			$raw     = (array) $request->get_json_params();
			$booking = KS_Booking_Request::from_array( $raw );
			$errors  = KS_Booking_Engine::validate( $booking );

			// Vehicle choice comes after pricing, so ignore vehicle-scoped errors here.
			unset( $errors['passengers'], $errors['bags'] );

			if ( $errors ) {
				return new WP_REST_Response( [ 'ok' => false, 'errors' => $errors ], 200 );
			}

			$options = KS_Booking_Engine::options( $booking );

			return new WP_REST_Response( [
				'ok'      => true,
				'options' => $options,
				'message' => $options ? '' : 'No online rate covers that route yet — send us a message and we will quote it.',
			], 200 );
		},
	] );

	register_rest_route( 'ks/v1', '/booking', [
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => function ( WP_REST_Request $request ) {
			if ( ! wp_verify_nonce( $request->get_header( 'X-WP-Nonce' ), 'wp_rest' ) ) {
				return new WP_REST_Response( [ 'ok' => false, 'errors' => [ 'form' => 'Your session expired. Reload the page and try again.' ] ], 403 );
			}

			$result = KS_Booking_Woo::add_to_cart( (array) $request->get_json_params() );

			if ( is_wp_error( $result ) ) {
				$errors = [];
				foreach ( $result->get_error_codes() as $code ) {
					$errors[ $code ] = $result->get_error_message( $code );
				}
				return new WP_REST_Response( [ 'ok' => false, 'errors' => $errors ], 200 );
			}

			return new WP_REST_Response( [ 'ok' => true, 'redirect' => wc_get_checkout_url() ], 200 );
		},
	] );
} );
