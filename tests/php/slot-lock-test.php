<?php
/**
 * Lightweight contract test for the booking lock. No WordPress install required.
 * Run: php tests/php/slot-lock-test.php
 */
define( 'ABSPATH', __DIR__ );
define( 'MINUTE_IN_SECONDS', 60 );
function add_action(...$args) {}
function apply_filters($name, $value) { return $value; }
function esc_sql($value) { return $value; }
function gmdate_stub() {}
class WP_Error {
	public function __construct(public string $code, public string $message) {}
}
class FakeDB {
	public string $prefix = 'wp_';
	public int $insert_id = 17;
	public array $queries = [];
	public bool $locked = true;
	public bool $overlap = false;
	public int $insert_count = 0;
	public function prepare($query, ...$args) {
		return vsprintf(str_replace(['%s','%d'], ["'%s'",'%d'], $query), $args);
	}
	public function get_var($query) {
		$this->queries[] = $query;
		if (str_contains($query, 'GET_LOCK')) return $this->locked ? 1 : 0;
		if (str_contains($query, 'RELEASE_LOCK')) return 1;
		if (str_contains($query, 'COUNT(*)')) return $this->overlap ? 1 : 0;
		return null;
	}
	public function query($query) { $this->queries[] = $query; return 1; }
	public function insert($table, $row) { $this->insert_count++; return 1; }
}
$wpdb = new FakeDB();
require __DIR__ . '/../../wordpress/klickspark-base/inc/booking/store.php';
$start = new DateTimeImmutable('2030-01-01 09:00:00', new DateTimeZone('UTC'));
$first = KS_Slot_Store::hold(3, 9, $start, 60, 'cart-1');
if ($first !== 17 || $wpdb->insert_count !== 1) throw new Exception('Expected first hold');
if (!str_contains(implode(' ', $wpdb->queries), 'RELEASE_LOCK')) throw new Exception('Lock not released');
$wpdb->overlap = true;
$second = KS_Slot_Store::hold(3, 9, $start->modify('+30 minutes'), 60, 'cart-2');
if (!($second instanceof WP_Error) || $second->code !== 'slot_taken' || $wpdb->insert_count !== 1) throw new Exception('Overlapping hold was not rejected');
$wpdb->locked = false;
$third = KS_Slot_Store::hold(3, 9, $start->modify('+2 hours'), 60, 'cart-3');
if (!($third instanceof WP_Error) || $third->code !== 'slot_busy') throw new Exception('Lock failure should fail closed');
echo "Slot lock contract checks passed\n";
