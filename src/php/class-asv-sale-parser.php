<?php
/**
 * Fetch sale metadata.
 *
 * @package AutoSaleVPS
 */

if ( ! class_exists( 'ASV_Sale_Parser' ) ) {
	class ASV_Sale_Parser {
		/**
		 * Fetch metadata from sale URL.
		 *
		 * @param string $sale_url Sale link.
		 * @return array
		 */
		public function extract_meta( $sale_url ) {
			if ( empty( $sale_url ) ) {
				return array();
			}

			$response = wp_remote_get( $sale_url, array( 'timeout' => 20 ) );

			if ( is_wp_error( $response ) ) {
				return array();
			}

			$body = wp_remote_retrieve_body( $response );
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
			$nodes = $xpath->query( '//*[contains(@class, "product-info")]' );
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
				$li_nodes = $xpath->query( '//li' );
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
