<?php
/**
 * Plugin Name: AutoSaleVPS
 * Description: 基于 LLM 的 VPS 推广助手，提供配置管理、可用性校验与前端展示。
 * Version: 0.1.0
 * Author: AutoSale Team
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/src/php/class-asv-config-repository.php';
require_once __DIR__ . '/src/php/class-asv-sale-parser.php';
require_once __DIR__ . '/src/php/class-asv-availability-service.php';
require_once __DIR__ . '/src/php/class-asv-promo-service.php';
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
			$config_path = plugin_dir_path( __FILE__ ) . 'config/config.toml';
			$model_path  = plugin_dir_path( __FILE__ ) . 'config/model.toml';

			$this->repository = new ASV_Config_Repository( $config_path, $model_path );

			$parser       = new ASV_Sale_Parser();
			$availability = new ASV_Availability_Service( $this->repository );
			$promo        = new ASV_Promo_Service( $this->repository );
			$this->rest_controller = new ASV_REST_Controller( $this->repository, $parser, $availability, $promo );

			add_action( 'init', array( $this, 'register_shortcode' ) );
			add_action( 'rest_api_init', array( $this->rest_controller, 'register_routes' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		}

		/**
		 * Register shortcode handler.
		 */
		public function register_shortcode() {
			add_shortcode( 'AutoSaleVPS', array( $this, 'render_shortcode' ) );
		}

		/**
		 * Register front-end assets.
		 */
		public function register_assets() {
			$script = plugins_url( 'assets/js/main.js', __FILE__ );
			$style  = plugins_url( 'assets/css/main.css', __FILE__ );

			wp_register_script( 'autosalevps-app', $script, array(), self::VERSION, true );
			wp_register_style( 'autosalevps-style', $style, array(), self::VERSION );
		}

		/**
		 * Render shortcode output.
		 *
		 * @return string
		 */
		public function render_shortcode() {
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
				)
			);

			ob_start();
			?>
			<div id="asv-root" class="asv-root" data-version="<?php echo esc_attr( self::VERSION ); ?>">
				<div class="asv-loading">正在载入 AutoSaleVPS...</div>
			</div>
			<?php
			return ob_get_clean();
		}
	}
}

$autosale_plugin = new AutoSaleVPS_Plugin();
$autosale_plugin->register();
