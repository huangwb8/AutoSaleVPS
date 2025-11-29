<?php
/**
 * Config repository utilities.
 *
 * @package AutoSaleVPS
 */

require_once __DIR__ . '/class-asv-toml.php';

if ( ! class_exists( 'ASV_Config_Repository' ) ) {
	class ASV_Config_Repository {
		const OPTION_API_KEY      = 'autosalevps_api_key';
		const OPTION_TIMEZONE     = 'autosalevps_timezone';
		const OPTION_STATUSES     = 'autosalevps_statuses';
		const OPTION_PROMO_CACHE  = 'autosalevps_promos';
		const DEFAULT_TIMEZONE    = 'Asia/Shanghai';

		/**
		 * Config path.
		 *
		 * @var string
		 */
		protected $config_path;

		/**
		 * Model path.
		 *
		 * @var string
		 */
		protected $model_path;

		/**
		 * Constructor.
		 *
		 * @param string $config_path Config path.
		 * @param string $model_path  Model path.
		 */
		public function __construct( $config_path, $model_path ) {
			$this->config_path = $config_path;
			$this->model_path  = $model_path;
		}

		/**
		 * Return raw config.
		 *
		 * @return string
		 */
		public function get_config_raw() {
			return (string) $this->read_file( $this->config_path );
		}

		/**
		 * Persist config.
		 *
		 * @param string $content Config contents.
		 */
		public function save_config_raw( $content ) {
			$this->write_file( $this->config_path, (string) $content );
		}

		/**
		 * Raw model file.
		 *
		 * @return string
		 */
		public function get_model_raw() {
			return (string) $this->read_file( $this->model_path );
		}

		/**
		 * Save model file.
		 *
		 * @param string $content File body.
		 */
		public function save_model_raw( $content ) {
			$this->write_file( $this->model_path, (string) $content );
		}

		/**
		 * Read parsed config.
		 *
		 * @return array
		 */
		public function get_config() {
			return ASV_TOML_Parser::parse( $this->get_config_raw() );
		}

		/**
		 * Parsed model file.
		 *
		 * @return array
		 */
		public function get_model() {
			return ASV_TOML_Parser::parse( $this->get_model_raw() );
		}

		/**
		 * Build Vps definitions from config.
		 *
		 * @return array
		 */
		public function get_vps_definitions() {
			$config   = $this->get_config();
			$vps_tree = isset( $config['vps'] ) && is_array( $config['vps'] ) ? $config['vps'] : array();
			$urls     = isset( $config['url'] ) && is_array( $config['url'] ) ? $config['url'] : array();
			$aff      = isset( $config['aff'] ) && is_array( $config['aff'] ) ? $config['aff'] : array();
			$result   = array();

			foreach ( $vps_tree as $vendor => $group ) {
				if ( ! is_array( $group ) ) {
					continue;
				}

				foreach ( $group as $pid => $details ) {
					$pid_value = isset( $details['pid'] ) ? (string) $details['pid'] : (string) $pid;
					$result[]  = array(
						'vendor'        => (string) $vendor,
						'pid'           => $pid_value,
						'human_comment' => isset( $details['human_comment'] ) ? (string) $details['human_comment'] : '',
						'sale_url'      => $this->build_url( $urls, $aff, $vendor, $pid_value, 'sale_format' ),
						'valid_url'     => $this->build_url( $urls, $aff, $vendor, $pid_value, 'valid_format' ),
						'interval'      => $this->extract_interval( $urls, $vendor ),
						'valid_delay'   => $this->extract_valid_delay( $urls, $vendor ),
					);
				}
			}

			return $result;
		}

		/**
		 * Build a URL using template.
		 *
		 * @param array  $urls    Url configuration.
		 * @param array  $aff     Affiliate codes.
		 * @param string $vendor  Vendor key.
		 * @param string $pid     Product id.
		 * @param string $key     Template key.
		 * @return string
		 */
		protected function build_url( $urls, $aff, $vendor, $pid, $key ) {
			if ( empty( $urls[ $vendor ][ $key ] ) ) {
				return '';
			}

			$aff_code = '';
			if ( ! empty( $aff[ $vendor ] ) && is_array( $aff[ $vendor ] ) ) {
				$aff_values = $aff[ $vendor ];
				$aff_code   = isset( $aff_values['code'] ) ? (string) $aff_values['code'] : implode( '', $aff_values );
			}

			$template = (string) $urls[ $vendor ][ $key ];
			return str_replace(
				array( '{aff}', '{pid}' ),
				array( rawurlencode( $aff_code ), rawurlencode( $pid ) ),
				$template
			);
		}

		/**
		 * Extract validation interval in seconds.
		 *
		 * @param array  $urls   Url config tree.
		 * @param string $vendor Vendor key.
		 * @return int
		 */
		protected function extract_interval( $urls, $vendor ) {
			if ( empty( $urls[ $vendor ]['valid_interval_time'] ) ) {
				return DAY_IN_SECONDS;
			}

			return (int) $urls[ $vendor ]['valid_interval_time'];
		}

		/**
		 * Extract a valid delay range.
		 *
		 * @param array  $urls   Url config.
		 * @param string $vendor Vendor key.
		 * @return array [min, max]
		 */
		protected function extract_valid_delay( $urls, $vendor ) {
			$raw = isset( $urls[ $vendor ]['valid_vps_time'] ) ? (string) $urls[ $vendor ]['valid_vps_time'] : '5-10';
			if ( false === strpos( $raw, '-' ) ) {
				$seconds = max( 1, (int) $raw );
				return array( $seconds, $seconds );
			}

			list( $min, $max ) = array_map( 'trim', explode( '-', $raw, 2 ) );
			$min               = max( 1, (int) $min );
			$max               = max( $min, (int) $max );
			return array( $min, $max );
		}

		/**
		 * Read a file safely.
		 *
		 * @param string $path File path.
		 * @return string
		 */
		protected function read_file( $path ) {
			if ( ! file_exists( $path ) ) {
				return '';
			}

			return (string) file_get_contents( $path );
		}

		/**
		 * Write file contents.
		 *
		 * @param string $path    File path.
		 * @param string $content Content.
		 */
		protected function write_file( $path, $content ) {
			wp_mkdir_p( dirname( $path ) );
			file_put_contents( $path, $content, LOCK_EX );
		}

		/**
		 * Fetch stored API key.
		 *
		 * @return string
		 */
		public function get_api_key() {
			return (string) get_option( self::OPTION_API_KEY, '' );
		}

		/**
		 * Save API key.
		 *
		 * @param string $key Secret.
		 */
		public function save_api_key( $key ) {
			update_option( self::OPTION_API_KEY, (string) $key );
		}

		/**
		 * Return timezone.
		 *
		 * @return string
		 */
		public function get_timezone() {
			return (string) get_option( self::OPTION_TIMEZONE, self::DEFAULT_TIMEZONE );
		}

		/**
		 * Update timezone.
		 *
		 * @param string $timezone Timezone id.
		 */
		public function save_timezone( $timezone ) {
			update_option( self::OPTION_TIMEZONE, (string) $timezone );
		}

		/**
		 * Persist statuses.
		 *
		 * @param array $statuses Status payload.
		 */
		public function save_statuses( $statuses ) {
			update_option( self::OPTION_STATUSES, $statuses );
		}

		/**
		 * Get saved statuses.
		 *
		 * @return array
		 */
		public function get_statuses() {
			$statuses = get_option( self::OPTION_STATUSES, array() );
			return is_array( $statuses ) ? $statuses : array();
		}

		/**
		 * Save promo data.
		 *
		 * @param array $data Promo map.
		 */
		public function save_promo_cache( $data ) {
			update_option( self::OPTION_PROMO_CACHE, $data );
		}

		/**
		 * Retrieve promo cache.
		 *
		 * @return array
		 */
		public function get_promo_cache() {
			$data = get_option( self::OPTION_PROMO_CACHE, array() );
			return is_array( $data ) ? $data : array();
		}
	}
}
