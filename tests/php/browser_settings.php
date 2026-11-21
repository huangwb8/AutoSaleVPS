<?php
/** Browser address validation and settings precedence, without network calls. */
class WP_Error {
	public function __construct( $code, $message, $data = array() ) {}
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function get_option( $key, $default = false ) { return $GLOBALS['options'][ $key ] ?? $default; }
function update_option( $key, $value, $autoload = null ) { $GLOBALS['options'][ $key ] = $value; return true; }
function ensure( $condition, $message ) {
	if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); }
}
require_once __DIR__ . '/../../src/php/class-asv-browser-settings.php';
$GLOBALS['options'] = array();
ensure( 'http://asv-browser:8191' === ASV_Browser_Settings::get_base_url(), 'Default is the Docker service address' );
ensure( 'http://asv-browser:8191/v1' === ASV_Browser_Settings::endpoint(), 'Transport automatically appends the API path' );
define( 'ASV_BROWSER_ENDPOINT', 'http://legacy-browser:8191/v1' );
ensure( 'http://legacy-browser:8191' === ASV_Browser_Settings::get_base_url(), 'Legacy mu-plugin remains a fallback' );
ensure( 'https://browser.example.net/proxy' === ASV_Browser_Settings::save( ' https://browser.example.net/proxy/v1/ ' ), 'Pasted API URL is normalized' );
ensure( 'https://browser.example.net/proxy/v1' === ASV_Browser_Settings::endpoint(), 'Saved UI value overrides the legacy constant' );
foreach ( array( 'ftp://browser:8191', 'file:///etc/passwd', 'http://user:password@browser:8191', 'http://browser:8191?token=secret', 'http://browser:8191#fragment', 'http://browser:0', 'http://browser:99999', "http://browser:8191\r\nX-Test: injected", array( 'http://browser:8191' ) ) as $invalid ) {
	ensure( is_wp_error( ASV_Browser_Settings::save( $invalid ) ), 'Invalid input must be rejected' );
	ensure( 'https://browser.example.net/proxy' === ASV_Browser_Settings::get_base_url(), 'Invalid save does not replace the working configuration' );
}
ensure( 'http://asv-browser:8191' === ASV_Browser_Settings::save( '' ), 'Empty value restores the requested default' );
ensure( 'http://asv-browser:8191' === ASV_Browser_Settings::get_base_url(), 'Explicit default overrides a legacy constant' );
echo "Browser settings tests passed\n";
