<?php
/**
 * Browser service settings shared by REST and the page transport.
 *
 * @package AutoSaleVPS
 */

class ASV_Browser_Settings {
	const OPTION = 'autosalevps_browser_base_url';
	const DEFAULT_BASE_URL = 'http://asv-browser:8191';

	public static function get_base_url() {
		$saved = get_option( self::OPTION, null );
		$value = null !== $saved ? $saved : ( defined( 'ASV_BROWSER_ENDPOINT' ) ? ASV_BROWSER_ENDPOINT : self::DEFAULT_BASE_URL );
		$base = self::normalize( $value );
		return is_wp_error( $base ) ? self::DEFAULT_BASE_URL : $base;
	}

	public static function endpoint() {
		return self::get_base_url() . '/v1';
	}

	public static function save( $value ) {
		$base = self::normalize( $value );
		if ( is_wp_error( $base ) ) {
			return $base;
		}
		update_option( self::OPTION, $base, false );
		if ( get_option( self::OPTION ) !== $base ) {
			return new WP_Error( 'browser_settings_save_failed', '采集设置保存失败，请重试', array( 'status' => 500 ) );
		}
		return $base;
	}

	protected static function normalize( $value ) {
		$error = new WP_Error( 'invalid_browser_url', '请输入有效的 HTTP 或 HTTPS 服务地址，不要包含用户名、密码、查询参数或片段', array( 'status' => 400 ) );
		if ( ! is_string( $value ) || preg_match( '/[\x00-\x1F\x7F]/', $value ) ) {
			return $error;
		}
		$value = trim( $value );
		if ( '' === $value ) {
			return self::DEFAULT_BASE_URL;
		}
		$parts = parse_url( $value );
		if ( ! $parts || ! filter_var( $value, FILTER_VALIDATE_URL ) || empty( $parts['host'] ) || ! in_array( strtolower( $parts['scheme'] ?? '' ), array( 'http', 'https' ), true ) || isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['query'] ) || isset( $parts['fragment'] ) || ( isset( $parts['port'] ) && $parts['port'] < 1 ) ) {
			return $error;
		}
		$value = rtrim( $value, '/' );
		return preg_replace( '~/v1$~', '', $value );
	}
}
