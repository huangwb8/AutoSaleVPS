<?php
require_once __DIR__ . '/../../src/php/class-asv-config-repository.php';
require_once __DIR__ . '/../../src/php/class-asv-availability-service.php';

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
	public function __construct() {}

	public function get_api_key() {
		return '';
	}
}

class Testable_ASV_Availability_Service extends ASV_Availability_Service {
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

echo "Availability service PHP tests passed\n";
