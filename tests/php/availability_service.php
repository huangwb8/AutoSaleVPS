<?php
require_once __DIR__ . '/../../src/php/class-asv-config-repository.php';
require_once __DIR__ . '/../../src/php/class-asv-availability-service.php';
class WP_Error {
	public function get_error_message() { return 'Cloudflare challenge'; }
	public function get_error_code() { return 'asv_blocked'; }
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
$test_options = array();
$test_cron_time = false;
function get_option( $key, $default = false ) { return $GLOBALS['test_options'][ $key ] ?? $default; }
function update_option( $key, $value ) { $GLOBALS['test_options'][ $key ] = $value; }
function delete_option( $key ) { unset( $GLOBALS['test_options'][ $key ] ); }
function wp_next_scheduled( $hook ) { return $GLOBALS['test_cron_time']; }
function wp_schedule_event( $timestamp, $interval, $hook ) { $GLOBALS['test_cron_time'] = $timestamp; }
function wp_unschedule_event( $timestamp, $hook ) { $GLOBALS['test_cron_time'] = false; }
function wp_parse_url( $url ) { return parse_url( $url ); }
class Fake_Page_Fetcher {
	public $response;
	public function fetch( $url, $delay ) { return $this->response; }
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $text ) {
		return trim( strip_tags( (string) $text ) );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, $options = 0 ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
		return json_encode( $data, $options );
	}
}

class ASV_Test_Config_Repository extends ASV_Config_Repository {
	public $statuses = array();
	public function __construct() {}
	public function get_statuses() { return $this->statuses; }

	public function get_api_key() {
		return '';
	}
}

class Testable_ASV_Availability_Service extends ASV_Availability_Service {
	public function public_scan( $body ) { return $this->basic_scan( $body ); }
	protected function build_llm_client( $provider ) {
		return new class { public function is_ready() { return false; } };
	}
	public function public_interpret_llm( $content ) {
		return $this->interpret_llm( $content );
	}

	public function public_build_llm_payload( $vps, $status_code, $headers, $body, $scan ) {
		return $this->build_llm_payload( $vps, $status_code, $headers, $body, $scan );
	}
}

function assert_condition( $condition, $message ) {
	if ( $condition ) {
		return;
	}

	file_put_contents( 'php://stderr', "[FAIL] $message\n" );
	exit( 1 );
}

$service = new Testable_ASV_Availability_Service( new ASV_Test_Config_Repository() );
assert_condition( 'unknown' === $service->public_scan( '' )['state'], 'Empty content is unknown, never sold out' );
assert_condition( 'unknown' === $service->public_scan( '<h1>Oops! A temporary error</h1>' )['state'], 'Generic errors are not stock evidence' );
assert_condition( 'online' === $service->public_scan( '<form id="frmConfigureProduct"><button id="btnCompleteProductConfig">Continue</button></form>' )['state'], 'WHMCS configuration page confirms an orderable product' );
assert_condition( 'offline' === $service->public_scan( '<h1>Out of stock</h1><form id="frmConfigureProduct"><button id="btnCompleteProductConfig">Continue</button></form>' )['state'], 'Explicit stock evidence takes priority' );
assert_condition( 'online' === $service->public_scan( '<p>A description (tagline) is not available for this product.</p><form id="frmConfigureProduct"><button id="btnCompleteProductConfig">Continue</button></form>' )['state'], 'Missing tagline on real RackNerd pages does not mean sold out' );

$analysis = $service->public_interpret_llm( '{"status":"offline","reason":"库存耗尽","confidence":0.9}' );
assert_condition( 'offline' === $analysis['state'], 'LLM JSON should set offline state' );
assert_condition( '库存耗尽' === $analysis['reason'], 'LLM JSON should keep reason' );
assert_condition( 0.9 === $analysis['confidence'], 'LLM JSON should expose confidence' );

$fallback = $service->public_interpret_llm( 'Page still TRUE for sale' );
assert_condition( 'online' === $fallback['state'], 'Fallback parsing should detect TRUE' );

$payload = $service->public_build_llm_payload(
	array(
		'vendor'    => 'racknerd',
		'pid'       => '123',
		'valid_url' => 'https://example.com',
	),
	200,
	array( 'content-type' => 'text/html' ),
	'<html><body><h1>In Stock</h1><p>Order now!</p></body></html>',
	array(
		'state'   => 'online',
		'reason'  => 'heuristic',
		'signals' => array(),
	)
);

$payload_array = json_decode( $payload, true );
assert_condition( 200 === $payload_array['status_code'], 'Payload should include HTTP status' );
assert_condition( 'racknerd' === $payload_array['vendor'], 'Payload should include vendor' );
assert_condition( ! empty( $payload_array['content_preview'] ), 'Payload should summarize body' );

$fetcher = new Fake_Page_Fetcher();
$fetcher->response = new WP_Error();
$validation = new Testable_ASV_Availability_Service( new ASV_Test_Config_Repository(), $fetcher );
$result = $validation->validate_vps( array( 'valid_url' => 'https://example.com' ), array() );
assert_condition( null === $result['available'], 'Challenge is unknown rather than sold out' );
$previous = array( 'available' => true, 'checked_at' => 123 );
$record = ASV_Availability_Service::status_record( $result, $previous, 456 );
assert_condition( true === $record['available'] && 123 === $record['checked_at'], 'Failed attempt preserves confirmed stock and timestamp' );
assert_condition( 456 === $record['attempted_at'] && 'asv_blocked' === $record['check_status'], 'Attempt has its own timestamp and status' );
$fetcher->response = array( 'body' => '<html>Unrecognized page</html>', 'status' => 200, 'headers' => array(), 'source' => 'http' );
$result = $validation->validate_vps( array( 'valid_url' => 'https://example.com' ), array() );
assert_condition( null === $result['available'], 'Unrecognized pages remain unknown without LLM' );
$fetcher->response['body'] = '<form id="frmConfigureProduct"><button id="btnCompleteProductConfig">Continue</button></form>';
$result = $validation->validate_vps( array( 'valid_url' => 'https://example.com' ), array() );
assert_condition( true === $result['available'], 'Normal WHMCS page confirms availability without LLM' );

require_once __DIR__ . '/../../src/php/class-asv-cron-scheduler.php';
class Testable_ASV_Cron extends ASV_Cron_Scheduler {
	public function due( $vps ) { return $this->should_check_vps( $vps ); }
}
$cron_repo = new ASV_Test_Config_Repository();
$cron = new Testable_ASV_Cron( $cron_repo, $validation );
$definition = array( 'vendor' => 'rn', 'pid' => '968', 'interval' => 172800 );
$cron_repo->statuses['rn-968'] = array( 'check_status' => 'asv_blocked', 'attempted_at' => time() - 301, 'checked_at' => time() - 1000 );
assert_condition( $cron->due( $definition ), 'Failed checks recover after cooldown rather than waiting the full stock interval' );
$cron_repo->statuses['rn-968']['attempted_at'] = time() - 60;
assert_condition( ! $cron->due( $definition ), 'Cron respects failure cooldown' );
$cron_repo->statuses['rn-968']['check_status'] = 'ok';
assert_condition( ! $cron->due( $definition ), 'Successful checks respect configured stock interval' );

$cron->maybe_schedule_cron();
assert_condition( $cron->is_cron_active(), 'Cron starts on first initialization' );
$cron->pause_cron();
$cron->maybe_schedule_cron();
assert_condition( ! $cron->is_cron_active(), 'A later request does not restart paused Cron' );
$cron->schedule_cron();
assert_condition( $cron->is_cron_active(), 'An administrator can resume Cron' );

$config = file_get_contents( __DIR__ . '/../../config/config.toml' );
assert_condition( ASV_Config_Repository::valid_url_formats( $config ), 'Bundled templates are valid' );
assert_condition( ! ASV_Config_Repository::valid_url_formats( str_replace( 'pid={pid}', 'pid=1', $config ) ), 'Static product URLs are rejected' );
assert_condition( ! ASV_Config_Repository::valid_url_formats( str_replace( 'https://', 'file://', $config ) ), 'Non-HTTP templates are rejected' );

echo "Availability service PHP tests passed\n";
