<?php
/** Isolated transport regression tests; no external requests. */
class WP_Error {
	private $code;
	private $message;
	public function __construct( $code, $message ) { $this->code = $code; $this->message = $message; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_remote_retrieve_body( $r ) { return $r['body'] ?? ''; }
function wp_remote_retrieve_response_code( $r ) { return $r['response']['code'] ?? 0; }
function wp_remote_retrieve_headers( $r ) { return $r['headers'] ?? array(); }
function wp_remote_retrieve_header( $r, $key ) { return $r['headers'][ $key ] ?? ''; }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function get_transient( $key ) { return $GLOBALS['transients'][ $key ] ?? false; }
function set_transient( $key, $value, $ttl ) { $GLOBALS['transients'][ $key ] = $value; }
function delete_transient( $key ) { unset( $GLOBALS['transients'][ $key ] ); }
function get_option( $key, $default = false ) { return $GLOBALS['options'][ $key ] ?? $default; }
function add_option( $key, $value, $unused = '', $autoload = false ) {
	if ( isset( $GLOBALS['options'][ $key ] ) ) { return false; }
	$GLOBALS['options'][ $key ] = $value;
	return true;
}
function delete_option( $key ) { unset( $GLOBALS['options'][ $key ] ); }
function wp_remote_get( $url, $args ) {
	$GLOBALS['direct_calls']++;
	return $GLOBALS['direct_response'];
}
function wp_remote_post( $url, $args ) {
	$GLOBALS['browser_endpoints'][] = $url;
	$GLOBALS['browser_calls'][] = json_decode( $args['body'], true );
	return $GLOBALS['browser_response'];
}
function check( $value, $message ) {
	if ( ! $value ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); }
}
function reset_transport() {
	$GLOBALS['transients'] = $GLOBALS['options'] = $GLOBALS['browser_calls'] = array();
	$GLOBALS['browser_endpoints'] = array();
	$GLOBALS['direct_calls'] = 0;
	$GLOBALS['direct_response'] = array( 'response' => array( 'code' => 200 ), 'headers' => array(), 'body' => '<h1>Product</h1><button>Add to Cart</button>' );
	$GLOBALS['browser_response'] = array( 'response' => array( 'code' => 200 ), 'body' => json_encode( array( 'status' => 'ok', 'solution' => array( 'status' => 200, 'response' => '<div class="product-info">2GB RAM</div><button>Add to Cart</button>' ) ) ) );
}
require_once __DIR__ . '/../../src/php/class-asv-page-fetcher.php';
$url = 'https://my.racknerd.com/cart.php?a=add&pid=968';
reset_transport();
$fetcher = new ASV_Page_Fetcher( 'http://browser:8191/v1' );
$r = $fetcher->fetch( $url, array( 1, 1 ) );
check( ! is_wp_error( $r ) && 'http' === $r['source'], 'Normal pages use HTTP' );
$fetcher->fetch( $url, array( 1, 1 ) );
check( 1 === $GLOBALS['direct_calls'], 'Cached page avoids a second request' );
reset_transport();
$GLOBALS['direct_response'] = array( 'response' => array( 'code' => 403 ), 'body' => '<title>Just a moment...</title><script src="/cdn-cgi/challenge-platform/test"></script>' );
$r = $fetcher->fetch( $url, array( 1, 1 ) );
check( ! is_wp_error( $r ) && 'browser' === $r['source'], '403 falls back to browser' );
check( $url === end( $GLOBALS['browser_calls'] )['url'], 'Browser receives validation URL' );
reset_transport();
$GLOBALS['direct_response'] = array( 'response' => array( 'code' => 200 ), 'headers' => array( 'cf-mitigated' => 'challenge' ), 'body' => '<h1>Checking your browser</h1>' );
$r = $fetcher->fetch( $url, array( 1, 1 ) );
check( ! is_wp_error( $r ) && 'browser' === $r['source'], '200 challenge also falls back' );
reset_transport();
$GLOBALS['direct_response'] = array( 'response' => array( 'code' => 403 ), 'body' => '<title>Just a moment...</title>' );
$GLOBALS['browser_response'] = array( 'response' => array( 'code' => 200 ), 'body' => '{"status":"error","message":"Challenge timeout"}' );
$r = $fetcher->fetch( $url, array( 1, 1 ) );
check( is_wp_error( $r ), 'Failed browser never yields product content' );
$calls = $GLOBALS['direct_calls'];
$r = $fetcher->fetch( str_replace( '968', '969', $url ), array( 1, 1 ) );
check( is_wp_error( $r ) && 'asv_cooldown' === $r->get_error_code(), 'Failure cools down the entire host' );
check( $calls === $GLOBALS['direct_calls'], 'Cooldown sends no requests' );
$GLOBALS['options'][ ASV_Browser_Settings::OPTION ] = 'http://replacement-browser:8191';
$GLOBALS['browser_response'] = array( 'response' => array( 'code' => 200 ), 'body' => '{"status":"ok","solution":{"status":200,"response":"<h1>Product</h1>"}}' );
unset( $GLOBALS['transients'][ 'asv_host_' . md5( 'my.racknerd.com' ) . '_next' ] );
check( ! is_wp_error( ( new ASV_Page_Fetcher() )->fetch( $url, array( 1, 1 ) ) ), 'Changing service can recover from the old service failure cooldown' );
reset_transport();
$GLOBALS['direct_response'] = array( 'response' => array( 'code' => 429 ), 'headers' => array( 'retry-after' => '120' ), 'body' => 'Too many requests' );
$r = $fetcher->fetch( $url, array( 1, 1 ) );
check( is_wp_error( $r ) && empty( $GLOBALS['browser_calls'] ), '429 honors rate limit without browser retry' );
$calls = $GLOBALS['direct_calls'];
$GLOBALS['options'][ ASV_Browser_Settings::OPTION ] = 'http://replacement-browser:8191';
check( is_wp_error( ( new ASV_Page_Fetcher() )->fetch( $url, array( 1, 1 ) ) ) && $calls === $GLOBALS['direct_calls'], 'Changing service still honors the vendor rate limit' );
reset_transport();
$GLOBALS['direct_response'] = array( 'response' => array( 'code' => 403 ), 'body' => 'Forbidden' );
$GLOBALS['browser_response'] = array( 'response' => array( 'code' => 200 ), 'body' => '{"status":"ok","solution":{"status":200,"response":"<title>Just a moment...</title>"}}' );
check( is_wp_error( $fetcher->fetch( $url, array( 1, 1 ) ) ), 'Browser challenge cannot be cached as a product' );
require_once __DIR__ . '/../../src/php/class-asv-sale-parser.php';
$parser = new ASV_Sale_Parser( $fetcher );
check( array() === $parser->parse_body( '<nav><ul><li>Login</li><li>Domains</li></ul></nav>' ), 'Navigation is not product metadata' );
check( array( '2GB RAM 40GB SSD' ) === $parser->parse_body( '<div class="product-info"><p>2GB RAM</p> <p>40GB SSD</p></div>' ), 'Product configuration is extracted' );
reset_transport();
$GLOBALS['direct_response']['body'] = '<div class="product-info">2GB RAM</div>';
check( array( '2GB RAM' ) === $parser->extract_meta( $url, array( 1, 1 ) ), 'Product metadata is retained' );
unset( $GLOBALS['transients'][ 'asv_page_' . hash( 'sha256', $url ) ] );
$GLOBALS['transients'][ 'asv_host_' . md5( 'my.racknerd.com' ) . '_cooldown' ] = true;
check( array( '2GB RAM' ) === $parser->extract_meta( $url, array( 1, 1 ) ), 'Temporary failure keeps known specifications' );
reset_transport();
$GLOBALS['options'][ ASV_Browser_Settings::OPTION ] = 'https://custom-browser.example.net:8191';
$GLOBALS['direct_response'] = array( 'response' => array( 'code' => 403 ), 'body' => 'Forbidden' );
$configured_fetcher = new ASV_Page_Fetcher();
$result = $configured_fetcher->fetch( $url, array( 1, 1 ) );
check( ! is_wp_error( $result ) && 'https://custom-browser.example.net:8191/v1' === end( $GLOBALS['browser_endpoints'] ), 'Actual browser transport uses the saved service address' );
echo "Page fetcher tests passed\n";
