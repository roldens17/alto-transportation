<?php
/**
 * One-command content seed: Places, Fleet and Rates that match the live
 * marketing site (ROUTES / VEHICLES / FLEET in src/app/App.tsx) exactly.
 *
 * The parent theme deliberately keeps content out of config.php — per the
 * top-level README, Places, Fleet and Rates are real posts, added by hand in
 * wp-admin. This command is the fast path to that same end state instead of
 * clicking through the admin twenty times: `wp alto seed`. It's idempotent —
 * matching rows are looked up by slug and updated in place, never duplicated.
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

class Alto_Seed_Command extends WP_CLI_Command {

	/**
	 * Creates/updates the Places, Fleet and Rates for Alto Private Transportation.
	 *
	 * ## OPTIONS
	 *
	 * [--skip-images]
	 * : Don't attempt to sideload vehicle photos from Unsplash.
	 *
	 * ## EXAMPLES
	 *
	 *     wp alto seed
	 *     wp alto seed --skip-images
	 */
	public function seed( array $args, array $assoc_args ): void {
		if ( ! function_exists( 'update_field' ) ) {
			WP_CLI::error( 'ACF Pro is required to seed field values. Activate it and try again.' );
		}

		$places   = $this->seed_places();
		$vehicles = $this->seed_vehicles( ! empty( $assoc_args['skip-images'] ) );
		$rates    = $this->seed_rates( $places, $vehicles );

		WP_CLI::success( sprintf(
			'Seeded %d place(s), %d vehicle(s), %d rate(s).',
			count( $places ),
			count( $vehicles ),
			$rates
		) );

		if ( ! ks_config( 'booking.product_id' ) ) {
			WP_CLI::warning( 'booking.product_id is still 0 in config.php — create the hidden "Booking" WooCommerce product (see wordpress/README.md) and put its ID there before going live.' );
		}
	}

	/** @return array<string,int> place slug => post ID */
	private function seed_places(): array {
		// Only MCO is a pickup and only destinations are drop-offs, because
		// that's the only direction the live site sells today (fixed
		// "From: MCO" in the booking widget). Flip these — and turn on
		// booking.round_trip in config.php — the day return trips ship;
		// rates are already marked bidirectional so no new Rate rows would
		// be needed.
		$rows = [
			'mco-orlando-international-airport' => [ 'title' => 'Orlando International Airport (MCO)', 'type' => 'airport', 'pickup' => true,  'dropoff' => false ],
			'walt-disney-world'                 => [ 'title' => 'Walt Disney World',                   'type' => 'area',    'pickup' => false, 'dropoff' => true ],
			'universal-orlando'                 => [ 'title' => 'Universal Orlando',                   'type' => 'area',    'pickup' => false, 'dropoff' => true ],
			'port-canaveral'                     => [ 'title' => 'Port Canaveral',                      'type' => 'port',    'pickup' => false, 'dropoff' => true ],
			'downtown-orlando'                   => [ 'title' => 'Downtown Orlando',                    'type' => 'area',    'pickup' => false, 'dropoff' => true ],
		];

		$ids = [];

		foreach ( $rows as $slug => $row ) {
			$id = $this->upsert_post( 'ks_place', $slug, $row['title'] );

			update_field( 'place_type', $row['type'], $id );
			update_field( 'is_pickup', $row['pickup'], $id );
			update_field( 'is_dropoff', $row['dropoff'], $id );

			$ids[ $slug ] = $id;
		}

		return $ids;
	}

	/** @return array<string,int> vehicle slug => post ID */
	private function seed_vehicles( bool $skip_images ): array {
		$rows = [
			'executive-sedan'    => [
				'title'   => 'Executive Sedan',
				'class'   => 'Lincoln Continental',
				'pax'     => 3,
				'bags'    => 3,
				'weight'  => 10,
				'image'   => 'https://images.unsplash.com/photo-1780296269675-169390638617?w=1200&auto=format&q=80',
				'excerpt' => 'Quiet, polished, understated. The right choice for solo travelers and couples who want reliability without excess. Car seats on request.',
			],
			'premium-suv'        => [
				'title'   => 'Premium SUV',
				'class'   => 'Cadillac Escalade',
				'pax'     => 6,
				'bags'    => 6,
				'weight'  => 20,
				'image'   => 'https://images.unsplash.com/photo-1769641241150-26c44a98e17a?w=1200&auto=format&q=80',
				'excerpt' => 'Designed for families. Third-row seating, generous cargo capacity, and car seats included.',
			],
			'executive-sprinter' => [
				'title'   => 'Executive Sprinter',
				'class'   => 'Mercedes-Benz Sprinter',
				'pax'     => 14,
				// 0 reads as "unlimited": KS_Booking_Engine::validate() only
				// enforces a bag cap when max_bags is truthy.
				'bags'    => 0,
				'weight'  => 30,
				'image'   => 'https://images.unsplash.com/photo-1700329694402-baa26366b29e?w=1200&auto=format&q=80',
				'excerpt' => 'Engineered for groups. Conference seating, overhead storage, full standing height, and an optional AV package.',
			],
		];

		$ids = [];

		foreach ( $rows as $slug => $row ) {
			$id = $this->upsert_post( 'ks_vehicle', $slug, $row['title'], $row['excerpt'] );

			update_field( 'vehicle_class', $row['class'], $id );
			update_field( 'max_passengers', $row['pax'], $id );
			update_field( 'max_bags', $row['bags'], $id );
			update_field( 'sort_weight', $row['weight'], $id );

			if ( ! $skip_images && ! has_post_thumbnail( $id ) ) {
				$this->maybe_sideload_thumbnail( $id, $row['image'], $row['title'] );
			}

			$ids[ $slug ] = $id;
		}

		return $ids;
	}

	/**
	 * @param array<string,int> $places   slug => post ID, from seed_places()
	 * @param array<string,int> $vehicles slug => post ID, from seed_vehicles()
	 */
	private function seed_rates( array $places, array $vehicles ): int {
		// One-way sedan price per destination, plus the flat per-vehicle
		// surcharge. Identical to ROUTES / VEHICLES in src/app/App.tsx —
		// e.g. Walt Disney World: $115 sedan, +$25 SUV, +$65 Sprinter.
		$routes = [
			'walt-disney-world' => [ 'label' => 'Walt Disney World', 'price' => 115 ],
			'universal-orlando' => [ 'label' => 'Universal Orlando', 'price' => 95 ],
			'port-canaveral'    => [ 'label' => 'Port Canaveral', 'price' => 139 ],
			'downtown-orlando'  => [ 'label' => 'Downtown Orlando', 'price' => 78 ],
		];

		$vehicle_surcharges = [
			'executive-sedan'    => [ 'label' => 'Executive Sedan', 'surcharge' => 0 ],
			'premium-suv'        => [ 'label' => 'Premium SUV', 'surcharge' => 25 ],
			'executive-sprinter' => [ 'label' => 'Executive Sprinter', 'surcharge' => 65 ],
		];

		$from_id    = $places['mco-orlando-international-airport'];
		$from_label = 'Orlando International Airport (MCO)';
		$count      = 0;

		foreach ( $routes as $place_slug => $route ) {
			foreach ( $vehicle_surcharges as $vehicle_slug => $vehicle ) {
				$slug  = "rate-mco-{$place_slug}-{$vehicle_slug}";
				$id    = $this->upsert_post( 'ks_rate', $slug, sprintf( '%s → %s (%s)', $from_label, $route['label'], $vehicle['label'] ) );
				$price = $route['price'] + $vehicle['surcharge'];

				update_field( 'from_place', $from_id, $id );
				update_field( 'to_place', $places[ $place_slug ], $id );
				update_field( 'vehicle', $vehicles[ $vehicle_slug ], $id );
				update_field( 'price', $price, $id );
				update_field( 'bidirectional', true, $id );
				update_field( 'is_active', true, $id );

				++$count;
			}
		}

		return $count;
	}

	private function upsert_post( string $post_type, string $slug, string $title, string $excerpt = '' ): int {
		$existing = get_posts( [
			'post_type'      => $post_type,
			'name'           => $slug,
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
		] );

		$postarr = [
			'post_type'    => $post_type,
			'post_name'    => $slug,
			'post_title'   => $title,
			'post_excerpt' => $excerpt,
			'post_status'  => 'publish',
		];

		if ( $existing ) {
			$postarr['ID'] = $existing[0];
			wp_update_post( $postarr );
			return (int) $existing[0];
		}

		return (int) wp_insert_post( $postarr );
	}

	private function maybe_sideload_thumbnail( int $post_id, string $url, string $title ): void {
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$attachment_id = media_sideload_image( $url, $post_id, $title, 'id' );

		if ( is_wp_error( $attachment_id ) ) {
			WP_CLI::warning( sprintf( 'Could not fetch photo for "%s": %s', $title, $attachment_id->get_error_message() ) );
			return;
		}

		set_post_thumbnail( $post_id, (int) $attachment_id );
	}
}

WP_CLI::add_command( 'alto', 'Alto_Seed_Command' );
