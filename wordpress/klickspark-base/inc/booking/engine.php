<?php
/**
 * Booking engine.
 *
 * The engine never talks to WooCommerce and never renders HTML. It takes a
 * raw request array, validates it, and returns a quote. That separation is
 * what makes it reusable across client sites and testable without WordPress
 * request context.
 */

defined( 'ABSPATH' ) || exit;

class KS_Booking_Request {
	public function __construct(
		public int $from = 0,
		public int $to = 0,
		public int $vehicle = 0,
		public string $date = '',
		public string $time = '',
		public bool $round_trip = false,
		public string $return_date = '',
		public string $return_time = '',
		public int $passengers = 1,
		public int $bags = 0,
		public int $stops = 0,
		public string $flight_no = '',
		// slot mode
		public int $service = 0,
		public int $practitioner = 0,
	) {}

	public static function from_array( array $raw ): self {
		return new self(
			from:        absint( $raw['from'] ?? 0 ),
			to:          absint( $raw['to'] ?? 0 ),
			vehicle:     absint( $raw['vehicle'] ?? 0 ),
			date:        sanitize_text_field( $raw['date'] ?? '' ),
			time:        sanitize_text_field( $raw['time'] ?? '' ),
			round_trip:  ! empty( $raw['round_trip'] ),
			return_date: sanitize_text_field( $raw['return_date'] ?? '' ),
			return_time: sanitize_text_field( $raw['return_time'] ?? '' ),
			passengers:  max( 1, absint( $raw['passengers'] ?? 1 ) ),
			bags:        absint( $raw['bags'] ?? 0 ),
			stops:       absint( $raw['stops'] ?? 0 ),
			flight_no:   sanitize_text_field( $raw['flight_no'] ?? '' ),
			service:     absint( $raw['service'] ?? 0 ),
			practitioner: absint( $raw['practitioner'] ?? 0 ),
		);
	}

	public function departs_at(): ?DateTimeImmutable {
		return $this->parse( $this->date, $this->time );
	}

	public function returns_at(): ?DateTimeImmutable {
		return $this->parse( $this->return_date, $this->return_time );
	}

	private function parse( string $date, string $time ): ?DateTimeImmutable {
		if ( ! $date ) {
			return null;
		}
		try {
			return new DateTimeImmutable( trim( "$date $time" ), wp_timezone() );
		} catch ( Exception ) {
			return null;
		}
	}
}

class KS_Booking_Engine {

	/**
	 * Validate a request. Returns a list of human-readable problems —
	 * empty means valid. Errors name what to fix, never just "invalid input".
	 */
	public static function validate( KS_Booking_Request $r ): array {
		$errors = [];
		$cfg    = ks_config( 'booking' );

		if ( 'route' !== $cfg['mode'] ) {
			// Non-route modes validate their own shape, then reuse the shared date rules.
			return apply_filters( 'ks/booking/validate', self::validate_timing( $r, $cfg ), $r );
		}

		if ( ! $r->from || ! $r->to ) {
			$errors['route'] = 'Choose both a pickup and a drop-off point.';
		} elseif ( $r->from === $r->to ) {
			$errors['route'] = 'Pickup and drop-off are the same place.';
		}

		$departs = $r->departs_at();
		if ( ! $departs ) {
			$errors['date'] = 'Add a pickup date and time.';
		} else {
			$earliest = new DateTimeImmutable( "+{$cfg['min_lead_hours']} hours", wp_timezone() );
			$latest   = new DateTimeImmutable( "+{$cfg['max_days_ahead']} days", wp_timezone() );

			if ( $departs < $earliest ) {
				$errors['date'] = sprintf(
					'Pickups need at least %d hours notice. Call us for anything sooner.',
					$cfg['min_lead_hours']
				);
			} elseif ( $departs > $latest ) {
				$errors['date'] = 'That date is too far ahead to book online.';
			}
		}

		if ( $r->round_trip ) {
			$returns = $r->returns_at();
			if ( ! $returns ) {
				$errors['return_date'] = 'Add a return date and time.';
			} elseif ( $departs && $returns <= $departs ) {
				$errors['return_date'] = 'The return has to be after the pickup.';
			}
		}

		if ( $r->vehicle ) {
			$capacity = self::capacity( $r->vehicle );
			if ( $r->passengers > $capacity['passengers'] ) {
				$errors['passengers'] = sprintf(
					'%s seats %d. Pick a larger vehicle for %d.',
					get_the_title( $r->vehicle ),
					$capacity['passengers'],
					$r->passengers
				);
			}
			if ( $capacity['bags'] && $r->bags > $capacity['bags'] ) {
				$errors['bags'] = sprintf( '%s holds %d suitcases.', get_the_title( $r->vehicle ), $capacity['bags'] );
			}
		}

		if ( $cfg['require_flight_no'] && ! $r->flight_no && self::involves_airport( $r ) ) {
			$errors['flight_no'] = 'Add your flight number so we can track delays.';
		}

		return apply_filters( 'ks/booking/validate', $errors, $r );
	}

	/** Lead time and horizon rules, shared by every mode. */
	public static function validate_timing( KS_Booking_Request $r, array $cfg ): array {
		$errors  = [];
		$departs = $r->departs_at();

		if ( ! $departs ) {
			$errors['date'] = 'Choose a date and time.';
			return $errors;
		}

		$earliest = new DateTimeImmutable( "+{$cfg['min_lead_hours']} hours", wp_timezone() );
		$latest   = new DateTimeImmutable( "+{$cfg['max_days_ahead']} days", wp_timezone() );

		if ( $departs < $earliest ) {
			$errors['date'] = sprintf( 'The earliest we can take online is %s.', wp_date( 'D j M, g:ia', $earliest->getTimestamp() ) );
		} elseif ( $departs > $latest ) {
			$errors['date'] = 'That date is too far ahead to book online.';
		}

		return $errors;
	}

	/**
	 * Price a request. Returns null when no rate covers the route.
	 */
	public static function quote( KS_Booking_Request $r ): ?array {
		$mode  = ks_config( 'booking.mode' );
		$cfg   = ks_config( 'booking' );

		/** Swap in a different pricing strategy per client without touching the engine. */
		$base = apply_filters( "ks/booking/price/$mode", null, $r );

		if ( null === $base && 'route' === $mode ) {
			$base = self::route_price( $r );
		}

		if ( null === $base ) {
			return null;
		}

		$lines = [
			[
				'label'  => $r->round_trip ? 'Round trip transfer' : 'One-way transfer',
				'amount' => $r->round_trip ? round( $base * (float) $cfg['round_trip_modifier'], 2 ) : (float) $base,
			],
		];

		if ( $cfg['allow_stops'] && $r->stops > 0 ) {
			$lines[] = [
				'label'  => sprintf( '%d additional stop%s', $r->stops, 1 === $r->stops ? '' : 's' ),
				'amount' => round( $r->stops * (float) $cfg['stop_fee'], 2 ),
			];
		}

		$lines = apply_filters( 'ks/booking/quote_lines', $lines, $r );
		$total = array_sum( array_column( $lines, 'amount' ) );

		return [
			'lines'    => $lines,
			'total'    => round( $total, 2 ),
			'currency' => $cfg['currency'],
			'vehicle'  => $r->vehicle ? get_the_title( $r->vehicle ) : '',
		];
	}

	/**
	 * All vehicles that can carry the party, each with its price. This is what
	 * the front end renders as selectable cards.
	 */
	public static function options( KS_Booking_Request $r ): array {
		$vehicles = get_posts( [
			'post_type'      => 'ks_vehicle',
			'posts_per_page' => -1,
			'orderby'        => 'meta_value_num',
			'meta_key'       => 'sort_weight',
			'order'          => 'ASC',
		] );

		$options = [];

		foreach ( $vehicles as $vehicle ) {
			$capacity = self::capacity( $vehicle->ID );

			if ( $r->passengers > $capacity['passengers'] ) {
				continue;
			}
			if ( $capacity['bags'] && $r->bags > $capacity['bags'] ) {
				continue;
			}

			$candidate = clone $r;
			$candidate->vehicle = $vehicle->ID;
			$quote = self::quote( $candidate );

			if ( ! $quote ) {
				continue;
			}

			$options[] = [
				'vehicle_id' => $vehicle->ID,
				'name'       => get_the_title( $vehicle ),
				'class'      => (string) get_field( 'vehicle_class', $vehicle->ID ),
				'image'      => get_the_post_thumbnail_url( $vehicle, 'medium_large' ) ?: '',
				'capacity'   => $capacity,
				'total'      => $quote['total'],
				'currency'   => $quote['currency'],
			];
		}

		return $options;
	}

	private static function route_price( KS_Booking_Request $r ): ?float {
		if ( ! $r->from || ! $r->to || ! $r->vehicle ) {
			return null;
		}

		$cache_key = "ks_rate_{$r->from}_{$r->to}_{$r->vehicle}";
		$cached    = wp_cache_get( $cache_key, 'ks_booking' );
		if ( false !== $cached ) {
			return null === $cached ? null : (float) $cached;
		}

		$price = self::lookup_rate( $r->from, $r->to, $r->vehicle );

		// Reverse direction, when the rate row is marked bidirectional.
		if ( null === $price ) {
			$price = self::lookup_rate( $r->to, $r->from, $r->vehicle, true );
		}

		wp_cache_set( $cache_key, $price, 'ks_booking', HOUR_IN_SECONDS );

		return $price;
	}

	private static function lookup_rate( int $from, int $to, int $vehicle, bool $require_bidi = false ): ?float {
		$rates = get_posts( [
			'post_type'      => 'ks_rate',
			'posts_per_page' => 1,
			'post_status'    => 'publish',
			'fields'         => 'ids',
			'meta_query'     => [
				[ 'key' => 'from_place', 'value' => $from ],
				[ 'key' => 'to_place',   'value' => $to ],
				[ 'key' => 'vehicle',    'value' => $vehicle ],
				[ 'key' => 'is_active',  'value' => '1' ],
			],
		] );

		if ( ! $rates ) {
			return null;
		}

		$rate_id = $rates[0];

		if ( $require_bidi && ! get_field( 'bidirectional', $rate_id ) ) {
			return null;
		}

		$price = get_field( 'price', $rate_id );

		return null === $price ? null : (float) $price;
	}

	public static function capacity( int $vehicle_id ): array {
		return [
			'passengers' => (int) ( get_field( 'max_passengers', $vehicle_id ) ?: 0 ),
			'bags'       => (int) ( get_field( 'max_bags', $vehicle_id ) ?: 0 ),
			'carry_on'   => (int) ( get_field( 'max_carry_on', $vehicle_id ) ?: 0 ),
		];
	}

	private static function involves_airport( KS_Booking_Request $r ): bool {
		foreach ( [ $r->from, $r->to ] as $place_id ) {
			if ( $place_id && 'airport' === get_field( 'place_type', $place_id ) ) {
				return true;
			}
		}
		return false;
	}
}
