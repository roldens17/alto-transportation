<?php
/**
 * Server-rendered shell. The form is progressively enhanced: the markup below
 * is a working POST target, and view.js upgrades it to live pricing.
 *
 * @var array $attributes
 */

defined( 'ABSPATH' ) || exit;

if ( ! ks_module_enabled( 'booking' ) || 'route' !== ks_config( 'booking.mode' ) ) {
	return;
}

$ks_id   = wp_unique_id( 'ks-booking-' );
$ks_data = ks_booking_bootstrap_data();
$ks_cfg  = $ks_data['config'];
$ks_min  = ( new DateTimeImmutable( "+{$ks_cfg['minLeadHours']} hours", wp_timezone() ) )->format( 'Y-m-d' );
?>
<div
	<?php echo wp_kses_data( get_block_wrapper_attributes( [ 'class' => 'ks-booking' ] ) ); ?>
	id="<?php echo esc_attr( $ks_id ); ?>"
	data-ks-booking="<?php echo esc_attr( wp_json_encode( $ks_data ) ); ?>"
>
	<?php if ( ! empty( $attributes['heading'] ) ) : ?>
		<h2 class="ks-booking__heading"><?php echo esc_html( $attributes['heading'] ); ?></h2>
	<?php endif; ?>

	<div class="ks-booking__step" data-step="route">
		<div class="ks-booking__grid">
			<p class="ks-field">
				<label for="<?php echo esc_attr( $ks_id ); ?>-from">Pick up</label>
				<select id="<?php echo esc_attr( $ks_id ); ?>-from" name="from" data-ks-places="pickup" required>
					<option value="">Loading places…</option>
				</select>
			</p>

			<p class="ks-field">
				<label for="<?php echo esc_attr( $ks_id ); ?>-to">Drop off</label>
				<select id="<?php echo esc_attr( $ks_id ); ?>-to" name="to" data-ks-places="dropoff" required>
					<option value="">Loading places…</option>
				</select>
			</p>

			<p class="ks-field">
				<label for="<?php echo esc_attr( $ks_id ); ?>-date">Date</label>
				<input type="date" id="<?php echo esc_attr( $ks_id ); ?>-date" name="date" min="<?php echo esc_attr( $ks_min ); ?>" required>
			</p>

			<p class="ks-field">
				<label for="<?php echo esc_attr( $ks_id ); ?>-time">Time</label>
				<input type="time" id="<?php echo esc_attr( $ks_id ); ?>-time" name="time" step="<?php echo esc_attr( $ks_cfg['timeStep'] * 60 ); ?>" required>
			</p>

			<p class="ks-field">
				<label for="<?php echo esc_attr( $ks_id ); ?>-passengers">Passengers</label>
				<input type="number" id="<?php echo esc_attr( $ks_id ); ?>-passengers" name="passengers" value="2" min="1" max="60" required>
			</p>

			<p …5956 tokens truncated…t		'start' => new DateTimeImmutable( $start, wp_timezone() ),
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
	if ( ! ks_module_enabled( 'booking' ) || 'slot' !== ks_config( 'booking.mode' ) ) {
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

	// Paid endpoints must not exist on no-payment sites or without WooCommerce.
	if ( 'woo' !== ks_config( 'booking.payment', 'woo' ) || ! class_exists( 'WooCommerce' ) || ! class_exists( 'KS_Slot_Woo' ) ) {
		return;
	}
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
