<?php
/**
 * Shared, bounded HTTP/browser transport for anonymous product pages.
 *
 * @package AutoSaleVPS
 */
require_once __DIR__ . '/class-asv-browser-settings.php';

class ASV_Page_Fetcher {
	protected $endpoint;

	public function __construct( $endpoint = null ) {
		$this->endpoint = null !== $endpoint ? $endpoint : ASV_Browser_Settings::endpoint();
	}

	/** Only callers' configured validation URLs belong here; never affiliate links. */
	public function fetch( $url, $delay = array( 5, 10 ) ) {
		$host = strtolower( (string) parse_url( $url, PHP_URL_HOST ) );
		if ( ! $host || ! in_array( strtolower( (string) parse_url( $url, PHP_URL_SCHEME ) ), array( 'http', 'https' ), true ) ) {
			return new WP_Error( 'asv_url', '未配置有效的商品检查地址' );
		}
		$key = 'asv_page_' . hash( 'sha256', $url );
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$host_key = 'asv_host_' . md5( $host );
		$failure_key = $host_key . '_failed_' . md5( $this->endpoint );
		if ( get_transient( $host_key . '_cooldown' ) || get_transient( $failure_key ) ) {
			return new WP_Error( 'asv_cooldown', '厂商访问暂时受阻，队列冷却中；保留上次确认的库存' );
		}
		$lock_key = $host_key . '_lock';
		$owner = array( 'token' => uniqid( 'asv_', true ), 'expires' => time() + 180 );
		$existing = get_option( $lock_key );
		if ( is_array( $existing ) && $existing['expires'] < time() ) {
			delete_option( $lock_key );
		}
		if ( ! add_option( $lock_key, $owner, '', false ) ) {
			return new WP_Error( 'asv_busy', '同一厂商已有采集任务，请稍后重试' );
		}
		try {
			$next = (int) get_transient( $host_key . '_next' );
			if ( $next > time() ) {
				$wait = $next - time();
				if ( $wait > 60 ) {
					return new WP_Error( 'asv_busy', '厂商请求间隔尚未结束，请稍后重试' );
				}
				sleep( $wait );
			}
			$min = max( 1, (int) ( $delay[0] ?? 5 ) );
			$max = max( $min, (int) ( $delay[1] ?? 10 ) );
			$gap = rand( $min, $max );
			$response = wp_remote_get( $url, array( 'timeout' => 20, 'redirection' => 3, 'limit_response_size' => 1048576 ) );
			$page = $this->http_page( $response );
			if ( ! is_wp_error( $response ) && 429 === (int) wp_remote_retrieve_response_code( $response ) ) {
				$retry = wp_remote_retrieve_header( $response, 'retry-after' );
				$seconds = is_numeric( $retry ) ? (int) $retry : (int) strtotime( $retry ) - time();
				set_transient( $host_key . '_cooldown', true, max( 60, $seconds ) );
				return new WP_Error( 'asv_blocked', '厂商限制请求频率，已暂停该域名采集' );
			}
			if ( is_wp_error( $page ) && $this->endpoint ) {
				$page = $this->browser_page( $url );
			}
			set_transient( $host_key . '_next', time() + $gap, $gap + 120 );
			if ( is_wp_error( $page ) ) {
				set_transient( $failure_key, true, 300 );
				return $page;
			}
			// Only successful, non-challenge pages are cached. Cookies never leave the transport.
			set_transient( $key, $page, 120 );
			return $page;
		} finally {
			if ( get_option( $lock_key ) === $owner ) {
				delete_option( $lock_key );
			}
		}
	}

	protected function http_page( $response ) {
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'asv_transport', '商品页面请求失败，库存暂时无法确认' );
		}
		$headers = array();
		foreach ( array( 'content-type', 'cf-mitigated', 'server' ) as $name ) {
			$headers[ $name ] = wp_remote_retrieve_header( $response, $name );
		}
		$page = array(
			'body' => wp_remote_retrieve_body( $response ),
			'status' => (int) wp_remote_retrieve_response_code( $response ),
			'headers' => $headers,
			'source' => 'http',
		);
		return $this->verify_page( $page );
	}

	protected function browser_page( $url ) {
		$response = wp_remote_post( $this->endpoint, array(
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body' => wp_json_encode( array( 'cmd' => 'request.get', 'url' => $url, 'maxTimeout' => 45000 ) ),
			'timeout' => 55,
			'redirection' => 0,
			'limit_response_size' => 2097152,
		) );
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return new WP_Error( 'asv_browser', '浏览器采集服务暂时不可用，库存无法确认' );
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || 'ok' !== ( $data['status'] ?? '' ) || ! isset( $data['solution']['response'], $data['solution']['status'] ) ) {
			return new WP_Error( 'asv_blocked', '浏览器未能取得商品页面，已暂停该域名采集' );
		}
		return $this->verify_page( array(
			'body' => (string) $data['solution']['response'],
			'status' => (int) $data['solution']['status'],
			'headers' => array( 'content-type' => 'text/html' ),
			'source' => 'browser',
		) );
	}

	protected function verify_page( $page ) {
		if ( $page['status'] < 200 || $page['status'] >= 300 || '' === trim( $page['body'] ) ) {
			return new WP_Error( 'asv_blocked', '商品页面访问失败（HTTP ' . $page['status'] . '），库存无法确认' );
		}
		if ( 'challenge' === strtolower( (string) ( $page['headers']['cf-mitigated'] ?? '' ) ) || preg_match( '~<title[^>]*>\s*(?:Just a moment|Attention Required)|/cdn-cgi/challenge-platform/|cf-chl-|cf_chl_|checking your browser|enable javascript and cookies to continue~i', $page['body'] ) ) {
			return new WP_Error( 'asv_blocked', 'Cloudflare 验证拦截，库存无法确认' );
		}
		return $page;
	}
}
