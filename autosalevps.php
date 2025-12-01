<?php
/**
 * Plugin Name: AutoSaleVPS
 * Description: 基于 LLM 的 VPS 推广助手，提供配置管理、可用性校验与前端展示。
 * Version: 0.1.0
 * Author: Bensz Conan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/src/php/class-asv-config-repository.php';
require_once __DIR__ . '/src/php/class-asv-sale-parser.php';
require_once __DIR__ . '/src/php/class-asv-availability-service.php';
require_once __DIR__ . '/src/php/class-asv-promo-service.php';
require_once __DIR__ . '/src/php/class-asv-meta-service.php';
require_once __DIR__ . '/src/php/class-asv-rest-controller.php';

if ( ! class_exists( 'AutoSaleVPS_Plugin' ) ) {
	class AutoSaleVPS_Plugin {
		/**
		 * Plugin version.
		 */
		const VERSION = '0.1.0';

		/**
		 * Repository.
		 *
		 * @var ASV_Config_Repository
		 */
		protected $repository;

		/**
		 * REST controller.
		 *
		 * @var ASV_REST_Controller
		 */
		protected $rest_controller;

		/**
		 * Bootstrap plugin.
		 */
		public function register() {
			$storage_dir = $this->prepare_storage_dir();
			$defaults    = plugin_dir_path( __FILE__ ) . 'config/';
			$config_path = trailingslashit( $storage_dir ) . 'config.toml';
			$model_path  = trailingslashit( $storage_dir ) . 'model.toml';

			$this->seed_default_file( $config_path, $defaults . 'config.toml' );
			$this->seed_default_file( $model_path, $defaults . 'model.toml' );

			$this->repository = new ASV_Config_Repository( $config_path, $model_path );

			$parser       = new ASV_Sale_Parser();
			$availability = new ASV_Availability_Service( $this->repository );
			$promo        = new ASV_Promo_Service( $this->repository );
			$meta         = new ASV_Meta_Service( $this->repository );
			$this->rest_controller = new ASV_REST_Controller( $this->repository, $parser, $availability, $promo, $meta );

			add_action( 'init', array( $this, 'register_shortcode' ) );
			add_action( 'init', array( $this, 'register_assets' ) );
			add_action( 'rest_api_init', array( $this->rest_controller, 'register_routes' ) );
			add_action( 'admin_menu', array( $this, 'register_admin_page' ) );
		}

		/**
		 * Register shortcode handler.
		 */
		public function register_shortcode() {
			add_shortcode( 'AutoSaleVPS', array( $this, 'render_shortcode' ) );
		}

		/**
		 * Register shared assets.
		 */
		public function register_assets() {
			$script = plugins_url( 'assets/js/main.js', __FILE__ );
			$style  = plugins_url( 'assets/css/main.css', __FILE__ );

			wp_register_script( 'autosalevps-app', $script, array(), self::VERSION, true );
			wp_register_style( 'autosalevps-style', $style, array(), self::VERSION );
		}

		/**
		 * Register admin page.
		 */
		public function register_admin_page() {
			add_menu_page(
				'AutoSaleVPS',
				'AutoSaleVPS',
				'manage_options',
				'autosalevps',
				array( $this, 'render_admin_page' ),
				'dashicons-cloud'
			);
		}

		/**
		 * Render admin settings page.
		 */
		public function render_admin_page() {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			$this->enqueue_app_assets();

			echo '<div class="wrap">';
			echo '<h1>AutoSaleVPS</h1>';
			echo '<div id="asv-root" class="asv-root asv-root--admin" data-version="' . esc_attr( self::VERSION ) . '">';
			echo '<div class="asv-loading">正在载入 AutoSaleVPS，可以按Ctrl + r强制刷新..</div>';
			echo '</div>';
			echo '</div>';
		}

		/**
		 * Render shortcode output.
		 *
		 * @return string
		 */
		public function render_shortcode() {
			$this->enqueue_app_assets();

			ob_start();
			?>
			<div id="asv-root" class="asv-root" data-version="<?php echo esc_attr( self::VERSION ); ?>">
			<div class="asv-loading">正在载入 AutoSaleVPS，可以按Ctrl + r强制刷新..</div>
			</div>
			<?php
			return ob_get_clean();
		}

		/**
		 * Enqueue assets with bootstrap payload.
		 */
		protected function enqueue_app_assets() {
			wp_enqueue_script( 'autosalevps-app' );
			wp_enqueue_style( 'autosalevps-style' );

			$current_user_can_manage = current_user_can( 'manage_options' );
			wp_localize_script(
				'autosalevps-app',
				'ASV_BOOTSTRAP',
				array(
					'restUrl'   => esc_url_raw( rest_url( ASV_REST_Controller::NAMESPACE_SLUG ) ),
					'nonce'     => wp_create_nonce( 'wp_rest' ),
					'isAdmin'   => $current_user_can_manage,
					'timezone'  => $this->repository->get_timezone(),
					'version'   => self::VERSION,
					'hasKey'    => ! empty( $this->repository->get_api_key() ),
					'options'   => timezone_identifiers_list(),
					'extraCss'  => $this->repository->get_extra_css(),
				)
			);
		}

		/**
		 * Ensure storage directory exists under uploads.
		 *
		 * @return string
		 */
		protected function prepare_storage_dir() {
			$upload_dir = wp_upload_dir();
			$base       = trailingslashit( $upload_dir['basedir'] ) . 'autosalevps';
			wp_mkdir_p( $base );
			return $base;
		}

		/**
		 * Copy default file if target missing.
		 *
		 * @param string $target   Destination path.
		 * @param string $fallback Default file path.
		 */
		protected function seed_default_file( $target, $fallback ) {
			if ( file_exists( $target ) ) {
				return;
			}

			if ( file_exists( $fallback ) ) {
				wp_mkdir_p( dirname( $target ) );
				copy( $fallback, $target );
			}
		}
	}
}

$autosale_plugin = new AutoSaleVPS_Plugin();
$autosale_plugin->register();
