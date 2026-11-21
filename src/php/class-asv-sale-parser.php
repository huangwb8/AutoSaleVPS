<?php
/**
 * Fetch sale metadata.
 *
 * @package AutoSaleVPS
 */
require_once __DIR__ . '/class-asv-page-fetcher.php';

if ( ! class_exists( 'ASV_Sale_Parser' ) ) {
	class ASV_Sale_Parser {
		protected $fetcher;

		public function __construct( $fetcher = null ) {
			$this->fetcher = $fetcher ?? new ASV_Page_Fetcher();
		}
		/**
		 * Fetch metadata from sale URL.
		 *
		 * @param string $sale_url Sale link.
		 * @return array
		 */
		public function extract_meta( $sale_url, $delay = array( 5, 10 ) ) {
			if ( empty( $sale_url ) ) {
				return array();
			}

			$key = 'asv_meta_' . hash( 'sha256', $sale_url );
			$response = $this->fetcher->fetch( $sale_url, $delay );

			if ( is_wp_error( $response ) ) {
				$cached = get_transient( $key );
				return is_array( $cached ) ? $cached : array();
			}

			$lines = $this->parse_body( $response['body'] );
			if ( ! empty( $lines ) ) {
				set_transient( $key, $lines, 7 * 86400 );
			}
			return $lines;
		}

		public function parse_body( $body ) {
			if ( empty( $body ) ) {
				return array();
			}

			libxml_use_internal_errors( true );
			$doc = new DOMDocument();
			$loaded = $doc->loadHTML( $body );
			if ( ! $loaded ) {
				return array();
			}

			$xpath = new DOMXPath( $doc );
			$nodes = $xpath->query( '//*[contains(@class, "product-info") or contains(@class, "product-description") or contains(@class, "product-desc") or @id="productDescription"]' );
			$lines = array();

			if ( $nodes && $nodes->length > 0 ) {
				foreach ( $nodes as $node ) {
					$chunk = trim( preg_replace( '/\s+/', ' ', $node->textContent ) );
					if ( ! empty( $chunk ) ) {
						$lines[] = $chunk;
					}
				}
			}

			if ( empty( $lines ) ) {
				$li_nodes = $xpath->query( '//*[@id="order-standard_cart"]//li' );
				foreach ( $li_nodes as $node ) {
					$text = trim( preg_replace( '/\s+/', ' ', $node->textContent ) );
					if ( $text ) {
						$lines[] = $text;
					}
				}
			}

			return array_slice( $lines, 0, 12 );
		}
	}
}
