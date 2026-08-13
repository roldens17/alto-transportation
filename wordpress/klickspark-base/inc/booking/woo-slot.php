<?php
/**
 * Slot mode → WooCommerce.
 *
 * Same container-product pattern as route mode, plus a hold on the slot so
 * nobody else can take it while the patient is on the payment screen.
 */

defined( 'ABSPATH' ) || exit;

class KS_Slot_Woo {

	const CART_KEY = 'ks_appointment';

	public static function init(): void {
		if ( 'slot' !== ks_config( 'booking.mode' ) || ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		add_filter( 'woocommerce_add_cart_item_data', [ self::class, 'unique_line' ], 10, 2 );
		add_filter( 'woocommerce_get_item_data', [ self::class, 'display_in_cart' ], 10, 2 );
		add_action( 'woocommerce_before_calculate_totals', [ self::class, 'apply_price' ], 20 );
		add_action( 'woocommerce_checkout_create_order_line_item', [ self::class, 'save_to_order' ], 10, 4 );
		add_filter( 'woocommerce_cart_item_name', [ self::class, 'cart_item_name' ], 10, 3 );

		// Keep holds and cart in step.
		add_action( 'woocommerce_cart_item_removed', [ self::class, 'on_removed' ], 10, 2 );
		add_action( 'woocommerce_checkout_order_created', [ self::class, 'on_order_created' ] );
		add_action( 'woocommerce_order_status_processing', [ 'KS_Slot_Store', 'confirm' ] );
		add_action( 'woocommerce_order_status_completed', [ 'KS_Slot_Store', 'confirm' ] );
		add_action( 'woocommerce_order_status_cancelled', [ 'KS_Slot_Store', 'cancel_order' ] );
		add_action( 'woocommerce_order_status_failed', [ 'KS_Slot_Store', 'cancel_order' ] );

		add_filter( 'woocommerce_cart_needs_shipping', '__return_false' );
		add_filter( 'woocommerce_is_sold_individually', '__return_true' );

		// Re-check the slot at checkout in case a hold expired mid-payment.
		add_action( 'woocommerce_check_cart_items', [ self::class, 'revalidate_cart' ] );
	}

	public static function add_to_cart( array $raw ) {
		$request = KS_Booking_Request::from_array( $raw );

		if ( ! $request->service ) {
			return new WP_Error( 'service', 'Choose a service.' );
		}
		if ( ! $request->practitioner ) {
			return new WP_Error( 'practitioner', 'Choose who you would like to see.' );
		}

		$errors = KS_Booking_Engine::validate( $request );

		if ( $errors ) {
			$error = new WP_Error();
			foreach ( $errors as $field => $message ) {
				$error->add( $field, $message );
			}
			return $error;
		}

		$start = $request->departs_at();

		if ( ! self::is_open( $request, $start ) ) {
			return new WP_Error( 'date', 'That time is no longer open. Pick another.' );
		}

		$product_id = (int) ks_config( 'booking.product_id' );

		if ( ! $product_id ) {
			return new WP_Error( 'not_configured', 'Booking is not finished setting up on this site.' );
		}

		$duration = KS_Slot_Engine::duration( $request->service );
		$cart_key = wp_generate_uuid4();
		$hold     = KS_Slot_Store::hold( $request->practitioner, $request->service, $start, $duration, $cart_key );

		if ( is_wp_error( $hold ) ) {
			return $hold;
		}

		$added = WC()->cart->add_to_cart( $product_id, 1, 0, [], [
			self::CART_KEY => [
				'service'        => $request->service,
				'practitioner'   => $request->practitioner,
				'start'          => $start->format( 'Y-m-d H:i' ),
				'duration'       => $duration,
				'price'          => KS_Slot_Engine::price( $request->service ),
				'appointment_id' => $hold,
				'cart_key'       => $cart_key,
			],
		] );

		if ( ! $added ) {
			KS_Slot_Store::release( $cart_key );
			return new WP_Error( 'cart_failed', 'We could not hold that time. Try again.' );
		}

		return $added;
	}

	/** Is this exact start still offered by the availability engine? */
	private static function is_open( KS_Booking_Request $r, DateTimeImmutable $start ): bool {
		$slots = KS_Slot_Engine::slots_for_day( $r->practitioner, $r->service, $start->setTime( 0, 0 ) );

		foreach ( $slots as $slot ) {
			if ( $slot->format( 'Y-m-d H:i' ) === $start->format( 'Y-m-d H:i' ) ) {
				return true;
			}
		}

		return false;
	}

	public static function unique_line( array $data, int $product_id ): array {
		if ( isset( $data[ self::CART_KEY ] ) ) {
			$data['ks_unique'] = $data[ self::CART_KEY ]['cart_key'];
		}
		return $data;
	}

	public static function apply_price( $cart ): void {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		foreach ( $cart->get_cart() as $item ) {
			if ( isset( $item[ self::CART_KEY ]['price'] ) ) {
				$item['data']->set_price( (float) $item[ self::CART_KEY ]['price'] );
			}
		}
	}

	public static function cart_item_name( $name, $cart_item, $key ) {
		$appointment = $cart_item[ self::CART_KEY ] ?? null;

		return $appointment ? esc_html( get_the_title( (int) $appointment['service'] ) ) : $name;
	}

	public static function display_in_cart( array $item_data, array $cart_item ): array {
		$appointment = $cart_item[ self::CART_KEY ] ?? null;

		if ( ! $appointment ) {
			return $item_data;
		}

		foreach ( self::summarise( $appointment ) as $label => $value ) {
			$item_data[] = [ 'name' => $label, 'value' => $value ];
		}

		return $item_data;
	}

	public static function save_to_order( $item, $key, $values, $order ): void {
		$appointment = $values[ self::CART_KEY ] ?? null;

		if ( ! $appointment ) {
			return;
		}

		foreach ( self::summarise( $appointment ) as $label => $value ) {
			$item->add_meta_data( $label, $value );
		}

		$item->add_meta_data( '_ks_appointment', $appointment, true );
	}

	public static function on_order_created( $order ): void {
		foreach ( WC()->cart->get_cart() as $item ) {
			$appointment = $item[ self::CART_KEY ] ?? null;

			if ( ! $appointment ) {
				continue;
			}

			KS_Slot_Store::attach_order( $appointment['cart_key'], $order->get_id(), [
				'name'  => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
				'email' => $order->get_billing_email(),
				'notes' => $order->get_customer_note(),
			] );
		}
	}

	public static function on_removed( $key, $cart ): void {
		$item        = $cart->removed_cart_contents[ $key ] ?? [];
		$appointment = $item[ self::CART_KEY ] ?? null;

		if ( $appointment ) {
			KS_Slot_Store::release( $appointment['cart_key'] );
		}
	}

	/**
	 * If a hold lapsed while the patient was paying, say so on the cart rather
	 * than double-booking the practitioner.
	 */
	public static function revalidate_cart(): void {
		foreach ( WC()->cart->get_cart() as $key => $item ) {
			$appointment = $item[ self::CART_KEY ] ?? null;

			if ( ! $appointment ) {
				continue;
			}

			$start = new DateTimeImmutable( $appointment['start'], wp_timezone() );
			$end   = $start->modify( '+' . (int) $appointment['duration'] . ' minutes' );

			global $wpdb;
			$still_held = $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM " . KS_Slot_Store::table() . " WHERE cart_key = %s AND status IN ('held','confirmed')",
				$appointment['cart_key']
			) );

			if ( ! $still_held ) {
				WC()->cart->remove_cart_item( $key );
				wc_add_notice(
					sprintf(
						'Your hold on %s expired and the time was released. Choose a new time.',
						wp_date( 'D j M, g:ia', $start->getTimestamp() )
					),
					'error'
				);
			}
		}
	}

	public static function summarise( array $appointment ): array {
		$start = new DateTimeImmutable( $appointment['start'], wp_timezone() );

		return array_filter( [
			'With'     => get_the_title( (int) $appointment['practitioner'] ),
			'When'     => wp_date( 'l j F, g:ia', $start->getTimestamp() ),
			'Duration' => $appointment['duration'] . ' minutes',
		] );
	}
}

add_action( 'init', [ 'KS_Slot_Woo', 'init' ] );
