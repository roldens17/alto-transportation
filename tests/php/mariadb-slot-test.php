<?php
/** Real MariaDB booking lock integration tests.
 * Requires KS_TEST_DB_HOST, KS_TEST_DB_USER, KS_TEST_DB_PASSWORD, KS_TEST_DB_NAME.
 * Run: php tests/php/mariadb-slot-test.php
 */
define('ABSPATH', __DIR__);
define('MINUTE_IN_SECONDS', 60);
function add_action(...$args) {}
function apply_filters($name, $value) { return $value; }
function esc_sql($value) { return addslashes($value); }
class WP_Error {
    public function __construct(public string $code, public string $message) {}
}
class RealDB {
    public string $prefix = 'wp_';
    public int $insert_id = 0;
    public mysqli $db;
    public function __construct() {
        $this->db = new mysqli(getenv('KS_TEST_DB_HOST') ?: '127.0.0.1', getenv('KS_TEST_DB_USER') ?: 'root', getenv('KS_TEST_DB_PASSWORD') ?: '', getenv('KS_TEST_DB_NAME') ?: 'klickspark', (int)(getenv('KS_TEST_DB_PORT') ?: 3306));
        $this->db->query("SET time_zone = '+00:00'");
    }
    public function prepare($sql, ...$args) {
        foreach ($args as &$arg) $arg = is_int($arg) ? (string)$arg : "'" . $this->db->real_escape_string((string)$arg) . "'";
        unset($arg);
        return vsprintf(str_replace(['%s','%d'], ['%s','%s'], $sql), $args);
    }
    public function get_var($sql) {
        $result = $this->db->query($sql);
        if (!$result) return null;
        $row = $result->fetch_row();
        return $row[0] ?? null;
    }
    public function query($sql) { return $this->db->query($sql); }
    public function insert($table, $data) {
        $columns = implode(', ', array_map(fn($c) => "`$c`", array_keys($data)));
        $values = implode(', ', array_map(fn($v) => "'" . $this->db->real_escape_string((string)$v) . "'", array_values($data)));
        $ok = $this->db->query("INSERT INTO $table ($columns) VALUES ($values)");
        if ($ok) $this->insert_id = $this->db->insert_id;
        return $ok;
    }
}
$wpdb = new RealDB();
require __DIR__ . '/../../wordpress/klickspark-base/inc/booking/store.php';
$wpdb->query('CREATE TABLE IF NOT EXISTS wp_ks_appointments (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 practitioner_id BIGINT UNSIGNED NOT NULL,
 service_id BIGINT UNSIGNED NOT NULL,
 start_utc DATETIME NOT NULL,
 end_utc DATETIME NOT NULL,
 status VARCHAR(20) NOT NULL,
 hold_expires_utc DATETIME NULL,
 cart_key VARCHAR(64) NULL,
 created_utc DATETIME NOT NULL,
 UNIQUE KEY no_double_booking (practitioner_id, start_utc, status)
) ENGINE=InnoDB');
$wpdb->query('TRUNCATE TABLE wp_ks_appointments');
$start = new DateTimeImmutable('2030-01-01 09:00:00', new DateTimeZone('UTC'));
function assert_hold($result, bool $expected, string $description): void {
    if (($result instanceof WP_Error) === $expected) throw new RuntimeException($description);
}
assert_hold(KS_Slot_Store::hold(1, 5, $start, 60, 'first'), true, 'First reservation rejected');
assert_hold(KS_Slot_Store::hold(1, 5, $start->modify('+30 minutes'), 60, 'overlap'), false, 'Overlapping reservation accepted');
assert_hold(KS_Slot_Store::hold(1, 5, $start->modify('+60 minutes'), 60, 'adjacent'), true, 'Adjacent reservation rejected');
assert_hold(KS_Slot_Store::hold(2, 5, $start, 60, 'different-crew'), true, 'Independent crew rejected');
$wpdb->query('TRUNCATE TABLE wp_ks_appointments');
// Independent PHP processes, hence independent MariaDB connections, race for same crew.
$workers = [];
$workerFile = __DIR__ . '/mariadb-slot-worker.php';
for ($i=0; $i<8; $i++) {
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($workerFile) . ' ' . escapeshellarg((string)$i);
    $process = proc_open($command, [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
    if (!is_resource($process)) throw new RuntimeException('Could not spawn worker');
    fclose($pipes[0]);
    $workers[] = [$process, $pipes];
}
$successes = 0;
foreach ($workers as [$process,$pipes]) {
    $output = trim(stream_get_contents($pipes[1]));
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    $code = proc_close($process);
    if ($code !== 0) throw new RuntimeException('Worker crashed: ' . $error);
    if ($output === 'HELD') $successes++;
    elseif ($output !== 'TAKEN' && $output !== 'BUSY') throw new RuntimeException('Unexpected worker result: ' . $output);
}
$count = (int)$wpdb->get_var("SELECT COUNT(*) FROM wp_ks_appointments WHERE status='held'");
if ($successes !== 1 || $count !== 1) throw new RuntimeException("Race allowed $successes successes and $count holds");
echo "MariaDB slot integration passed: 8 competing processes, 1 reservation\n";
