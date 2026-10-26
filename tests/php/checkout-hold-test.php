<?php
/** Checkout hold revalidation contract; no WordPress runtime required. */
define('ABSPATH', __DIR__);
function add_action(...$args) {}
function wp_timezone() { return new DateTimeZone('UTC'); }
function gmdate_stub() {}
function wc_add_notice($message, $type) { $GLOBALS['notices'][] = [$message, $type]; }
function wp_date($format, $timestamp) { return gmdate($format, $timestamp); }
class CheckoutFakeDB {
    public string $prefix = 'wp_';
    public bool $valid = false;
    public array $queries = [];
    public function prepare($query, ...$args) {
        $escaped = array_map(fn($arg) => is_int($arg) ? $arg : "'" . str_replace("'", "''", $arg) . "'", $args);
        return vsprintf(str_replace(['%s','%d'], ['%s','%s'], $query), $escaped);
    }
    public function get_var($query) { $this->queries[] = $query; return $this->valid ? 42 : null; }
}
class KS_Slot_Store { public static function table() { return 'wp_ks_appointments'; } }
class FakeCart {
    public array $items;
    public array $removed = [];
    public function __construct($item) { $this->items = ['abc' => $item]; }
    public function get_cart() { return $this->items; }
    public function remove_cart_item($key) { $this->removed[] = $key; unset($this->items[$key]); }
}
class FakeWC { public FakeCart $cart; }
function WC() { return $GLOBALS['wc']; }
$wpdb = new CheckoutFakeDB();
require __DIR__ . '/../../wordpress/klickspark-base/inc/booking/woo-slot.php';
$appointment = ['service'=>5,'start'=>'2030-01-01 09:00','duration'=>60,'appointment_id'=>42,'cart_key'=>'test-cart'];
$GLOBALS['wc'] = new FakeWC();
$GLOBALS['wc']->cart = new FakeCart([KS_Slot_Woo::CART_KEY => $appointment]);
KS_Slot_Woo::revalidate_cart();
if ($GLOBALS['wc']->cart->removed !== ['abc']) throw new Exception('Expired hold was not removed');
if (count($GLOBALS['notices'] ?? []) !== 1) throw new Exception('Missing expiration notice');
$query = implode(' ', $wpdb->queries);
foreach (['id = 42','cart_key =', 'hold_expires_utc >', "status = 'held'"] as $fragment) {
    if (!str_contains($query, $fragment)) throw new Exception("Missing checkout validation: $fragment");
}
$wpdb->valid = true;
$GLOBALS['wc']->cart = new FakeCart([KS_Slot_Woo::CART_KEY => $appointment]);
KS_Slot_Woo::revalidate_cart();
if ($GLOBALS['wc']->cart->removed) throw new Exception('Valid hold was removed');
echo "Checkout hold validation checks passed\n";
