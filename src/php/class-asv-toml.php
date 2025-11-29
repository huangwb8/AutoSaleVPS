<?php
/**
 * Basic TOML parser for nested tables and key/value pairs.
 *
 * @package AutoSaleVPS
 */

if ( ! class_exists( 'ASV_TOML_Parser' ) ) {
	class ASV_TOML_Parser {
		/**
		 * Parse TOML string into associative array.
		 *
		 * @param string $content Raw TOML.
		 * @return array
		 */
		public static function parse( $content ) {
			$result       = array();
			$current_path = array();
			$lines        = preg_split( '/\r?\n/', (string) $content );

			foreach ( $lines as $line ) {
				$line = trim( $line );

				if ( '' === $line || 0 === strpos( $line, '#' ) ) {
					continue;
				}

				if ( '[' === substr( $line, 0, 1 ) && ']' === substr( $line, -1 ) ) {
					$segment      = trim( $line, '[]' );
					$current_path = array_map( 'trim', explode( '.', $segment ) );
					continue;
				}

				if ( false === strpos( $line, '=' ) ) {
					continue;
				}

				list( $key, $value ) = array_map( 'trim', explode( '=', $line, 2 ) );
				self::assign_value( $result, array_merge( $current_path, array( $key ) ), self::coerce_value( $value ) );
			}

			return $result;
		}

		/**
		 * Assign value to nested array path.
		 *
		 * @param array $target Target array (by reference).
		 * @param array $path   Path segments.
		 * @param mixed $value  Value to set.
		 */
		protected static function assign_value( array &$target, array $path, $value ) {
			$segment = array_shift( $path );
			if ( null === $segment ) {
				return;
			}

			if ( empty( $path ) ) {
				$target[ $segment ] = $value;
				return;
			}

			if ( ! isset( $target[ $segment ] ) || ! is_array( $target[ $segment ] ) ) {
				$target[ $segment ] = array();
			}

			self::assign_value( $target[ $segment ], $path, $value );
		}

		/**
		 * Convert scalar TOML value to PHP type.
		 *
		 * @param string $value Value fragment.
		 * @return mixed
		 */
		protected static function coerce_value( $value ) {
			$trimmed = trim( $value );

			if ( ( 0 === strpos( $trimmed, "'" ) && "'" === substr( $trimmed, -1 ) ) || ( 0 === strpos( $trimmed, '"' ) && '"' === substr( $trimmed, -1 ) ) ) {
				return stripcslashes( substr( $trimmed, 1, -1 ) );
			}

			if ( is_numeric( $trimmed ) ) {
				return (int) $trimmed;
			}

			if ( 'true' === strtolower( $trimmed ) ) {
				return true;
			}

			if ( 'false' === strtolower( $trimmed ) ) {
				return false;
			}

			return $trimmed;
		}
	}
}
