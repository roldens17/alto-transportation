<?php
/**
 * Slot mode: date + time against a practitioner's calendar.
 *
 * Availability is computed, never stored: working hours, minus time off,
 * minus existing appointments, minus lead time, stepped by the service's
 * slot interval. Nothing to keep in sync.
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/store.php';

class KS_Slot_Engine {

	/**
	 * Open start times for one practitioner on one date, in site time.
	 *
	 * @return DateTimeImmutable[]
	 */
	public static function slots_for_day( int $practitioner, int $service, DateTimeImmutable $day ): array {
		$duration = self::duration( $service );
		$buffer   = (int) ( get_field( 'buffer_minutes', $service ) ?: 0 );
		$step     = (int) ( get_field( 'slot_interval', $practitioner ) ?: ks_config( 'booking.time_step_minutes', 15 ) );

		if ( ! $duration || ! self::serves( $practitioner, $service ) ) {
			return [];
		}

		$earliest = new DateTimeImmutable( '+' . (int) ks_config( 'booking.min_lead_hours', 2 ) . ' hours', wp_timezone() );
		$slots    = [];

		foreach ( self::shifts( $practitioner, $day ) as $shift ) {
			$cursor = $shift['start'];
			$busy   = KS_Slot_Store::busy( $practitioner, $shift['start'], $shift['end'] );
			$off    = self::time_off( $practitioner, $shift['start'], $shift['end'] );

			while ( true ) {
				$end = $cursor->modify( '+' . ( $duration + $buffer ) . ' minutes' );

				if ( $end > $shift['end'] ) {
					break;
				}

				if ( $cursor >= $earliest && ! self::collides( $cursor, $end, $busy ) && ! self::collides( $cursor, $end, $off ) ) {
					$slots[] = $cursor;
				}

				$cursor = $cursor->modify( "+{$step} minutes" );
			}
		}

		return apply_filters( 'ks/slots/for_day', $slots, $practitioner, $service, $day );
	}

	/**
	 * Availability across a date range, grouped by date then practitioner.
	 * Pass practitioner 0 for "any available".
	 */
	public static function availability( int $service, int $practitioner, DateTimeImmutable $from, int $days = 14 ): array {
		$practitioners = $practitioner ? [ $practitioner ] : self::practitioners_for( $service );
		$out           = [];

		for ( $i = 0; $i < $days; $i++ ) {
			$day   = $from->modify( "+{$i} days" )->setTime( 0, 0 );
			$found = [];

			foreach ( $practitioners as $id ) {
				foreach ( self::slots_for_day( $id, $service, $day ) as $slot ) {
					$found[ $slot->format( 'H:i' ) ][] = $id;
				}
			}

			ksort( $found );

			$out[] = [
				'date'  => $day->format( 'Y-m-d' ),
				'label' => wp_date( 'D j M', $day->getTimestamp() ),
				'slots' => array_map(
					fn( $time, $ids ) => [
						'time'          => $time,
						'practitioners' => array_values( array_unique( $ids ) ),
					],
					array_keys( $found ),
					$found
				),
			];
		}

		return $out;
	}

	/** Working shifts for a weekday, from the practitioner's hours repeater. */
	private static function shifts( int $practitioner, DateTimeImmutable $day ): array {
		$hours   = get_field( 'working_hours', $practitioner ) ?: [];
		$weekday = strtolower( $day->format( 'D' ) );   // mon, tue…
		$shifts  = [];

		foreach ( $hours as $row ) {
			if ( strtolower( $row['day'] ?? '' ) !== $weekday ) {
				continue;
			}

			$start = self::at( $day, $row['start_time'] ?? '' );
			$end   = self::at( $day, $row['end_time'] ?? '' );

			if ( $start && $end && $end > $start ) {
				$shifts[] = [ 'start' => $start, 'end' => $end ];
			}
		}

		return $shifts;
	}

	private static function time_off( int $practitioner, DateTimeImmutable $from, DateTimeImmutable $to ): array {
		$posts = get_posts( [
			'post_type'      => 'ks_timeoff',
			'posts_per_page' => -1,
			'meta_query'     => [ [ 'key' => 'practitioner', 'value' => $practitioner ] ],
		] );

		$blocks = [];

		foreach ( $posts as $post ) {
			$start = get_field( 'start_datetime', $post->ID );
			$end   = get_field( 'end_datetime', $post->ID );

			if ( ! $start || ! $end ) {
				continue;
			}

			try {
				$block = [
					'start' => new DateTimeImmutable( $start, wp_timezone() ),
					'end'   => new DateTimeImmutable( $end, wp_timezone() ),
				];
			} catch ( Exception ) {
				continue;
			}

			if ( $block['end'] > $from && $block['start'] < $to ) {
				$blocks[] = $block;
			}
		}

		return $blocks;
	}

	private static function collides( DateTimeImmutable $start, DateTimeImmutable $end, array $blocks ): bool {
		foreach ( $blocks as $block ) {
			if ( $start < $block['end'] && $end > $block['start'] ) {
				return true;
			}
		}
		return false;
	}

	private static function at( DateTimeImmutable $day, string $time ): ?DateTimeImmutable {
		if ( ! preg_match( '/^(\d{1,2}):(\d{2})/', $time, $m ) ) {
			return null;
		}
		return $day->setTime( (int) $m[1], (int) $m[2] );
	}

	public static function duration( int $service ): int {
		return (int) ( get_field( 'duration_minutes', $service ) ?: 0 );
	}

	public static function price( int $service ): float {
		return (float) ( get_field( 'price', $service ) ?: 0 );
	}

	public static function serves( int $practitioner, int $service ): bool {
		$services = get_field( 'services', $practitioner ) ?: [];
		$ids      = array_map( fn( $s ) => is_object( $s ) ? $s->ID : (int) $s, $services );

		return in_array( $service, $ids, true );
	}

	public static function practitioners_for( int $service ): array {
		$all = get_posts( [ 'post_type' => 'ks_practitioner', 'posts_per_page' => -1, 'fields' => 'ids' ] );

		return array_values( array_filter( $all, fn( $id ) => self::serves( $id, $service ) ) );
	}
}

/** Slot-mode pricing: flat per service. Override per client with this filter. */
add_filter( 'ks/booking/price/slot', function ( $price, KS_Booking_Request $r ) {
	$service = (int) ( $r->service ?? 0 );
	return $service ? KS_Slot_Engine::price( $service ) : $price;
}, 10, 2 );

/**
 * ACF groups for the scheduling model.
 */
add_action( 'acf/init', function () {
	if ( ! function_exists( 'acf_add_local_field_group' ) || 'slot' !== ks_config( 'booking.mode' ) ) {
		return;
	}

	$days = [ 'mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday', 'thu' => 'Thursday', 'fri' => 'Friday', 'sat' => 'Saturday', 'sun' => 'Sunday' ];

	acf_add_local_field_group( [
		'key'      => 'group_ks_service',
		'title'    => 'Service',
		'location' => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'ks_service' ] ] ],
		'fields'   => [
			[ 'key' => 'field_ks_s_duration', 'label' => 'Duration (minutes)', 'name' => 'duration_minutes', 'type' => 'number', 'required' => 1, 'min' => 5, 'step' => 5, 'default_value' => 45 ],
			[ 'key' => 'field_ks_s_buffer',   'label' => 'Turnaround after (minutes)', 'name' => 'buffer_minutes', 'type' => 'number', 'min' => 0, 'step' => 5, 'default_value' => 10, 'instructions' => 'Cleaning, notes, changeover. Blocked but not billed.' ],
			[ 'key' => 'field_ks_s_price',    'label' => 'Price', 'name' => 'price', 'type' => 'number', 'min' => 0, 'step' => '0.01' ],
			[ 'key' => 'field_ks_s_intake',   'label' => 'First visit', 'name' => 'is_intake', 'type' => 'true_false', 'ui' => 1, 'instructions' => 'Shows the intake questions at checkout.' ],
		],
	] );

	acf_add_local_field_group( [
		'key'      => 'group_ks_practitioner',
		'title'    => 'Practitioner',
		'location' => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'ks_practitioner' ] ] ],
		'fields'   => [
			[ 'key' => 'field_ks_pr_credentials', 'label' => 'Credentials', 'name' => 'credentials', 'type' => 'text', 'instructions' => 'Shown after the name, e.g. DPT, OCS.' ],
			[ 'key' => 'field_ks_pr_services',    'label' => 'Provides', 'name' => 'services', 'type' => 'relationship', 'post_type' => [ 'ks_service' ], 'return_format' => 'id' ],
			[ 'key' => 'field_ks_pr_interval',    'label' => 'Appointment starts every (minutes)', 'name' => 'slot_interval', 'type' => 'number', 'default_value' => 15, 'min' => 5, 'step' => 5 ],
			[
				'key' => 'field_ks_pr_hours', 'label' => 'Working hours', 'name' => 'working_hours', 'type' => 'repeater',
				'layout' => 'table', 'button_label' => 'Add shift',
				'instructions' => 'One row per shift. Two rows on a day gives you a lunch break.',
				'sub_fields' => [
					[ 'key' => 'field_ks_pr_day',   'label' => 'Day',   'name' => 'day', 'type' => 'select', 'choices' => $days, 'required' => 1 ],
					[ 'key' => 'field_ks_pr_start', 'label' => 'From',  'name' => 'start_time', 'type' => 'time_picker', 'return_format' => 'H:i', 'required' => 1 ],
					[ 'key' => 'field_ks_pr_end',   'label' => 'To',    'name' => 'end_time',   'type' => 'time_picker', 'return_format' => 'H:i', 'required' => 1 ],
				],
			],
		],
	] );

	acf_add_local_field_group( [
		'key'      => 'group_ks_timeoff',
		'title'    => 'Time off',
		'location' => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'ks_timeoff' ] ] ],
		'fields'   => [
			[ 'key' => 'field_ks_to_who',   'label' => 'Practitioner', 'name' => 'practitioner', 'type' => 'post_object', 'post_type' => [ 'ks_practitioner' ], 'required' => 1, 'return_format' => 'id' ],
			[ 'key' => 'field_ks_to_start', 'label' => 'From', 'name' => 'start_datetime', 'type' => 'date_time_picker', 'return_format' => 'Y-m-d H:i:s', 'required' => 1 ],
			[ 'key' => 'field_ks_to_end',   'label' => 'To',   'name' => 'end_datetime',   'type' => 'date_time_picker', 'return_format' => 'Y-m-d H:i:s', 'required' => 1 ],
		],
	] );
} );

/**
 * Availability + appointment endpoints.
 */
add_action( 'rest_api_init', function () {
	if ( 'slot' !== ks_config( 'booking.mode' ) ) {
		return;
	}

	register_rest_route( 'ks/v1', '/availability', [
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'args'                => [
			'service'      => [ 'required' => true, 'sanitize_callback' => 'absint' ],
			'practitioner' => [ 'default' => 0, 'sanitize_callback' => 'absint' ],
			'from'         => [ 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ],
			'days'         => [ 'default' => 14, 'sanitize_callback' => 'absint' ],
		],
		'callback'            => function ( WP_REST_Request $request ) {
			$service = $request->get_param( 'service' );

			if ( ! KS_Slot_Engine::duration( $service ) ) {
				return new WP_REST_Response( [ 'ok' => false, 'errors' => [ 'service' => 'Choose a service first.' ] ], 200 );
			}

			try {
				$from = new DateTimeImmutable( $request->get_param( 'from' ) ?: 'today', wp_timezone() );
			} catch ( Exception ) {
				$from = new DateTimeImmutable( 'today', wp_timezone() );
			}

			return new WP_REST_Response( [
				'ok'       => true,
				'duration' => KS_Slot_Engine::duration( $service ),
				'price'    => KS_Slot_Engine::price( $service ),
				'days'     => KS_Slot_Engine::availability( $service, $request->get_param( 'practitioner' ), $from, min( 28, $request->get_param( 'days' ) ) ),
			], 200 );
		},
	] );

	register_rest_route( 'ks/v1', '/appointment', [
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => function ( WP_REST_Request $request ) {
			if ( ! wp_verify_nonce( $request->get_header( 'X-WP-Nonce' ), 'wp_rest' ) ) {
				return new WP_REST_Response( [ 'ok' => false, 'errors' => [ 'form' => 'Your session expired. Reload and try again.' ] ], 403 );
			}

			$result = KS_Slot_Woo::add_to_cart( (array) $request->get_json_params() );

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
