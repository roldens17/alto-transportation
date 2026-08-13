<?php
/**
 * WooCommerce integration.
 *
 * One hidden container product carries every booking. Price is set from the
 * engine at cart-calculation time, so rates can change without breaking
 * existing orders and there is no product-per-route explosion.
 */

defined( 'ABSPATH' ) || exit;

class KS_Booking_Woo {

	const CART_KEY = 'ks_booking';

	public static function init(): void {
		if ( ! ks_module_enabled( 'booking' ) || ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		add_filter( 'woocommerce_add_cart_item_data', [ self::class, 'attach_data' ], 10, 2 );
		add_filter( 'woocommerce_get_item_data', [ self::class, 'display_in_cart' ], 10, 2 );
		add_action( 'woocommerce_before_calculate_totals', [ self::class, 'apply_price' ], 20 );
		add_action( 'woocommerce_checkout_create_order_line_item', [ self::class, 'save_to_order' ], 10, 4 );
		add_filter( 'woocommerce_cart_item_name', [ self::class, 'cart_item_name' ], 10, 3 );

		// A booking is a booking — no quantity stepper, no shipping.
		add_filter( 'woocommerce_is_sold_individually', fn( $single, $product ) => self::is_booking_product( $product->get_id() ) ? true : $single, 10, 2 );
		add_filter( 'woocommerce_cart_needs_shipping', fn( $needs ) => self::cart_is_bookings_only() ? false : $needs );
	}

	public static function is_booking_product( int $product_id ): bool {
		return $product_id === (int) ks_config( 'booking.product_id' );
	}

	/**
	 * Add a validated booking to the cart. Returns the cart item key, or a
	 * WP_Error carrying field-level messages the form can render inline.
	 */
	public static function add_to_cart( array $raw ) {
		$request = KS_Booking_Request::from_array( $raw );
		$errors  = KS_Booking_Engine::validate( $request );

		if ( $errors ) {
			$error = new WP_Error();
			foreach ( $errors as $field => $message ) {
				$error->add( $field, $message );
			}
			return $error;
		}

		$quote = KS_Booking_Engine::quote( $request );

		if ( ! $quote ) {
			return new WP_Error( 'no_rate', 'We do not have an online rate for that route yet. Send us a message and we will quote it.' );
		}

		$product_id = (int) ks_config( 'booking.product_id' );

		if ( ! $product_id ) {
			return new WP_Error( 'not_configured', 'Booking is not finished setting up on this site.' );
		}

		$key = WC()->cart->add_to_cart( $product_id, 1, 0, [], [
			self::CART_KEY => [
				'request' => (array) $request,
				'quote'   => $quote,
			],
		] );

		return $key ?: new WP_Error( 'cart_failed', 'We could not start your booking. Try again.' );
	}

	public static function attach_data( array $data, int $product_id ): array {
		// Each booking is unique, so force a distinct cart line.
		if ( isset( $data[ self::CART_KEY ] ) ) {
			$data['ks_unique'] = wp_generate_uuid4();
		}
		return $data;
	}

	public static function apply_price( $cart ): void {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		foreach ( $cart->get_cart() as $item ) {
			if ( empty( $item[ self::CART_KEY ]['quote']['total'] ) ) {
				continue;
			}
			$item['data']->set_price( (float) $item[ self::CART_KEY ]['quote']['total'] );
		}
	}

	public static function display_in_cart( array $item_data, array $cart_item ): array {
		$booking = $cart_item[ self::CART_KEY ] ?? null;

		if ( ! $booking ) {
			return $item_data;
		}

		foreach ( self::summarise( $booking ) as $label => $value ) {
			$item_data[] = [ 'name' => $label, 'value' => $value ];
		}

		return $item_data;
	}

	public static function cart_item_name( $name, $cart_item, $key ) {
		$booking = $cart_item[ self::CART_KEY ] ?? null;

		if ( ! $booking ) {
			return $name;
		}

		return sprintf(
			'%s → %s',
			esc_html( get_the_title( (int) $booking['request']['from'] ) ),
			esc_html( get_the_title( (int) $booking['request']['to'] ) )
		);
	}

	public static function save_to_order( $item, $key, $values, $order ): void {
		$booking = $values[ self::CART_KEY ] ?? null;

		if ( ! $booking ) {
			return;
		}

		// Human-readable for the ops team and the customer's email.
		foreach ( self::summarise( $booking ) as $label => $value ) {
			$item->add_meta_data( $label, $value );
		}

		// Machine-readable for dispatch exports and integrations.
		$item->add_meta_data( '_ks_booking', $booking, true );
	}

	/**
	 * One place that turns a stored booking into labelled lines. Used by the
	 * cart, the order, the confirmation email and the admin screen so they can
	 * never drift apart.
	 */
	public static function summarise( array $booking ): array {
		$r = $booking['request'];

		$out = [
			'Pickup'     => get_the_title( (int) $r['from'] ),
			'Drop-off'   => get_the_title( (int) $r['to'] ),
			'Date'       => trim( $r['date'] . ' ' . $r['time'] ),
			'Passengers' => (string) $r['passengers'],
		];

		if ( ! empty( $r['vehicle'] ) ) {
			$out['Vehicle'] = get_the_title( (int) $r['vehicle'] );
		}
		if ( ! empty( $r['round_trip'] ) ) {
			$out['Return'] = trim( $r['return_date'] . ' ' . $r['return_time'] );
		}
		if ( ! empty( $r['bags'] ) ) {
			$out['Luggage'] = (string) $r['bags'];
		}
		if ( ! empty( $r['stops'] ) ) {
			$out['Extra stops'] = (string) $r['stops'];
		}
		if ( ! empty( $r['flight_no'] ) ) {
			$out['Flight'] = $r['flight_no'];
		}

		return array_filter( $out, fn( $v ) => '' !== $v );
	}

	private static function cart_is_bookings_only(): bool {
		if ( ! WC()->cart || WC()->cart->is_empty() ) {
			return false;
		}

		foreach ( WC()->cart->get_cart() as $item ) {
			if ( empty( $item[ self::CART_KEY ] ) ) {
				return false;
			}
		}

		return true;
	}
}

add_action( 'init', [ 'KS_Booking_Woo', 'init' ] );
