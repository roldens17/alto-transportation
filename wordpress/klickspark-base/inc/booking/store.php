<?php
/**
 * Slot storage.
 *
 * Appointments live in a custom table, not post meta. Availability queries
 * need range scans on start/end plus a uniqueness guarantee per practitioner,
 * and meta tables give you neither at a sane cost.
 *
 * Statuses: held (in cart, expires), confirmed (paid), cancelled.
 */

defined( 'ABSPATH' ) || exit;

class KS_Slot_Store {

	const VERSION  = '2';
	const HOLD_MIN = 15;

	/**
	 * Statuses that occupy a slot. 'pending' exists for firms that must run a
	 * conflict check before confirming — the time is off the board while a
	 * human decides.
	 *
	 * Note: the unique index cannot span statuses, so a pending row and a held
	 * row could in principle be inserted for the same start in the same
	 * millisecond. overlaps() closes that in practice; if a client books at a
	 * volume where it matters, move to SELECT ... FOR UPDATE in a transaction.
	 */
	public static function active_statuses(): array {
		return apply_filters( 'ks/slots/active_statuses', [ 'held', 'pending', 'confirmed' ] );
	}

	private static function status_sql(): string {
		return "'" . implode( "','", array_map( 'esc_sql', self::active_statuses() ) ) . "'";
	}

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'ks_appointments';
	}

	public static function init(): void {
		add_action( 'after_switch_theme', [ self::class, 'install' ] );
		add_action( 'admin_init', [ self::class, 'maybe_install' ] );

		add_action( 'ks_release_expired_holds', [ self::class, 'release_expired' ] );
		if ( ! wp_next_scheduled( 'ks_release_expired_holds' ) ) {
			wp_schedule_event( time() + 300, 'hourly', 'ks_release_expired_holds' );
		}
	}

	public static function maybe_install(): void {
		if ( get_option( 'ks_appointments_db' ) !== self::VERSION ) {
			self::install();
		}
	}

	public static function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table();
		$collate = $wpdb->get_charset_collate();

		dbDelta( "CREATE TABLE $table (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			practitioner_id BIGINT UNSIGNED NOT NULL,
			service_id BIGINT UNSIGNED NOT NULL,
			start_utc DATETIME NOT NULL,
			end_utc DATETIME NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'held',
			enquiry_id BIGINT UNSIGNED NULL,
			hold_expires_utc DATETIME NULL,
			order_id BIGINT UNSIGNED NULL,
			cart_key VARCHAR(64) NULL,
			patient_name VARCHAR(191) NULL,
			patient_email VARCHAR(191) NULL,
			notes TEXT NULL,
			created_utc DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY practitioner_window (practitioner_id, start_utc, end_utc),
			KEY status_idx (status),
			KEY order_idx (order_id),
			KEY enquiry_idx (enquiry_id),
			UNIQUE KEY no_double_booking (practitioner_id, start_utc, status)
		) $collate" );

		update_option( 'ks_appointments_db', self::VERSION );
	}

	/**
	 * Reserve a slot. Relies on the unique index to lose races cleanly rather
	 * than checking-then-inserting, which is where two patients get the same
	 * 9am.
	 */
	public static function hold( int $practitioner, int $service, DateTimeImmutable $start, int $duration, string $cart_key ) {
		global $wpdb;

		$start_utc = $start->setTimezone( new DateTimeZone( 'UTC' ) );
		$end_utc   = $start_utc->modify( "+{$duration} minutes" );

		// A longer existing appointment can overlap without sharing a start time.
		if ( self::overlaps( $practitioner, $start_utc, $end_utc ) ) {
			return new WP_Error( 'slot_taken', 'That time was just taken. Pick another.' );
		}

		$ok = $wpdb->insert( self::table(), [
			'practitioner_id'  => $practitioner,
			'service_id'       => $service,
			'start_utc'        => $start_utc->format( 'Y-m-d H:i:s' ),
			'end_utc'          => $end_utc->format( 'Y-m-d H:i:s' ),
			'status'           => 'held',
			'hold_expires_utc' => gmdate( 'Y-m-d H:i:s', time() + self::HOLD_MIN * MINUTE_IN_SECONDS ),
			'cart_key'         => $cart_key,
			'created_utc'      => gmdate( 'Y-m-d H:i:s' ),
		] );

		if ( ! $ok ) {
			return new WP_Error( 'slot_taken', 'That time was just taken. Pick another.' );
		}

		return (int) $wpdb->insert_id;
	}

	public static function overlaps( int $practitioner, DateTimeImmutable $start_utc, DateTimeImmutable $end_utc ): bool {
		global $wpdb;

		$count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM " . self::table() . "
			 WHERE practitioner_id = %d
			   AND status IN (" . self::status_sql() . ")
			   AND start_utc < %s
			   AND end_utc > %s",
			$practitioner,
			$end_utc->format( 'Y-m-d H:i:s' ),
			$start_utc->format( 'Y-m-d H:i:s' )
		) );

		return $count > 0;
	}

	/** Busy blocks for a practitioner in a window, in site time. */
	public static function busy( int $practitioner, DateTimeImmutable $from, DateTimeImmutable $to ): array {
		global $wpdb;

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT start_utc, end_utc FROM " . self::table() . "
			 WHERE practitioner_id = %d
			   AND status IN (" . self::status_sql() . ")
			   AND end_utc > %s
			   AND start_utc < %s",
			$practitioner,
			$from->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ),
			$to->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' )
		) );

		return array_map( fn( $row ) => [
			'start' => new DateTimeImmutable( $row->start_utc, new DateTimeZone( 'UTC' ) ),
			'end'   => new DateTimeImmutable( $row->end_utc, new DateTimeZone( 'UTC' ) ),
		], $rows ?: [] );
	}

	public static function confirm( int $order_id ): void {
		global $wpdb;

		$wpdb->query( $wpdb->prepare(
			"UPDATE " . self::table() . "
			 SET status = 'confirmed', hold_expires_utc = NULL
			 WHERE order_id = %d AND status = 'held'",
			$order_id
		) );
	}

	public static function attach_order( string $cart_key, int $order_id, array $patient = [] ): void {
		global $wpdb;

		$wpdb->update(
			self::table(),
			[
				'order_id'      => $order_id,
				'patient_name'  => $patient['name'] ?? null,
				'patient_email' => $patient['email'] ?? null,
				'notes'         => $patient['notes'] ?? null,
			],
			[ 'cart_key' => $cart_key, 'status' => 'held' ]
		);
	}

	/** Move a hold into the review queue. Used when payment is not the gate. */
	public static function mark_pending( int $id ): void {
		global $wpdb;
		$wpdb->update( self::table(), [ 'status' => 'pending', 'hold_expires_utc' => null ], [ 'id' => $id ] );
	}

	public static function approve( int $id ): void {
		global $wpdb;
		$wpdb->update( self::table(), [ 'status' => 'confirmed' ], [ 'id' => $id, 'status' => 'pending' ] );
	}

	public static function decline( int $id ): void {
		global $wpdb;
		$wpdb->update( self::table(), [ 'status' => 'cancelled' ], [ 'id' => $id ] );
	}

	public static function get( int $id ): ?object {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . self::table() . " WHERE id = %d", $id ) );
	}

	public static function by_status( string $status, int $limit = 100 ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM " . self::table() . " WHERE status = %s ORDER BY start_utc ASC LIMIT %d",
			$status,
			$limit
		) ) ?: [];
	}

	public static function release( string $cart_key ): void {
		global $wpdb;
		$wpdb->delete( self::table(), [ 'cart_key' => $cart_key, 'status' => 'held' ] );
	}

	public static function cancel_order( int $order_id ): void {
		global $wpdb;
		$wpdb->update( self::table(), [ 'status' => 'cancelled' ], [ 'order_id' => $order_id ] );
	}

	public static function release_expired(): void {
		global $wpdb;

		$wpdb->query( $wpdb->prepare(
			"DELETE FROM " . self::table() . "
			 WHERE status = 'held' AND hold_expires_utc IS NOT NULL AND hold_expires_utc < %s",
			gmdate( 'Y-m-d H:i:s' )
		) );
	}

	/** Upcoming appointments for the dispatch/front-desk screen. */
	public static function upcoming( int $days = 14, ?int $practitioner = null ): array {
		global $wpdb;

		$sql    = "SELECT * FROM " . self::table() . " WHERE status = 'confirmed' AND start_utc BETWEEN %s AND %s";
		$params = [ gmdate( 'Y-m-d H:i:s' ), gmdate( 'Y-m-d H:i:s', time() + $days * DAY_IN_SECONDS ) ];

		if ( $practitioner ) {
			$sql     .= " AND practitioner_id = %d";
			$params[] = $practitioner;
		}

		return $wpdb->get_results( $wpdb->prepare( $sql . " ORDER BY start_utc ASC", ...$params ) ) ?: [];
	}
}

add_action( 'after_setup_theme', [ 'KS_Slot_Store', 'init' ] );
