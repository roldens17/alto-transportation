<?php
define('ABSPATH', __DIR__);
define('MINUTE_IN_SECONDS', 60);
function add_action(...$args) {}
function apply_filters($name,$value) { return $value; }
function esc_sql($value) { return addslashes($value); }
class WP_Error { public function __construct(public string $code, public string $message) {} }
class RealDB {
 public string $prefix='wp_'; public int $insert_id=0; public mysqli $db;
 public function __construct() { $this->db=new mysqli(getenv('KS_TEST_DB_HOST') ?: '127.0.0.1',getenv('KS_TEST_DB_USER') ?: 'root',getenv('KS_TEST_DB_PASSWORD') ?: '',getenv('KS_TEST_DB_NAME') ?: 'klickspark',(int)(getenv('KS_TEST_DB_PORT') ?: 3306)); }
 public function prepare($sql,...$args) { foreach($args as &$arg) $arg=is_int($arg)?(string)$arg:"'".$this->db->real_escape_string((string)$arg)."'"; unset($arg); return vsprintf(str_replace(['%s','%d'],['%s','%s'],$sql),$args); }
 public function get_var($sql) { $r=$this->db->query($sql); if(!$r)return null; $row=$r->fetch_row();return $row[0]??null; }
 public function query($sql) {return $this->db->query($sql);}
 public function insert($table,$data) {$cols=implode(', ',array_map(fn($c)=>"`$c`",array_keys($data)));$vals=implode(', ',array_map(fn($v)=>"'".$this->db->real_escape_string((string)$v)."'",array_values($data)));$ok=$this->db->query("INSERT INTO $table ($cols) VALUES ($vals)");if($ok)$this->insert_id=$this->db->insert_id;return $ok;}
}
$wpdb=new RealDB();
require __DIR__.'/../../wordpress/klickspark-base/inc/booking/store.php';
$start=new DateTimeImmutable('2030-01-02 09:00:00',new DateTimeZone('UTC'));
$result=KS_Slot_Store::hold(7,5,$start,60,'race-'.($argv[1]??'0'));
echo $result instanceof WP_Error ? ($result->code==='slot_busy'?'BUSY':'TAKEN') : 'HELD';
