<?php
/**
 * REST API controller.
 *
 * @package AutoSaleVPS
 */

require_once __DIR__ . '/class-asv-config-repository.php';
require_once __DIR__ . '/class-asv-sale-parser.php';
require_once __DIR__ . '/class-asv-availability-service.php';
require_once __DIR__ . '/class-asv-promo-service.php';
require_once __DIR__ . '/class-asv-meta-service.php';

if ( ! class_exists( 'ASV_REST_Controller' ) ) {
	class ASV_REST_Controller {
		const NAMESPACE_SLUG = 'autosalevps/v1';

		/**
		 * Repository.
		 *
		 * @var ASV_Config_Repository
		 */
		protected $repository;

		/**
		 * Sale parser.
		 *
		 * @var ASV_Sale_Parser
		 */
		protected $parser;

		/**
		 * Availability.
		 *
		 * @var ASV_Availability_Service
		 */
		protected $availability;

		/**
		 * Promo helper.
		 *
		 * @var ASV_Promo_Service
		 */
		protected $promo;

		/**
		 * Meta helper.
		 *
		 * @var ASV_Meta_Service
		 */
		protected $meta;

		/**
		 * Constructor.
		 *
		 * @param ASV_Config_Repository    $repository Repo.
		 * @param ASV_Sale_Parser          $parser     Parser.
		 * @param ASV_Availability_Service $availability Availability helper.
		 * @param ASV_Promo_Service        $promo Promo helper.
		 * @param ASV_Meta_Service         $meta  Meta helper.
		 */
		public function __construct( $repository, $parser, $availability, $promo, $meta ) {
			$this->repository  = $repository;
			$this->parser      = $parser;
			$this->availability = $availability;
			$this->promo       = $promo;
			$this->meta        = $meta;
		}

		/**
		 * Register all routes.
		 */
		public function register_routes() {
			register_rest_route(
				self::NAMESPACE_SLUG,
				'/config',
				array(
					array(
						'methods'  => WP_REST_Server::READABLE,
						'callback' => array( $this, 'get_config' ),
						'permission_callback' => array( $this, 'can_manage' ),
					),
					array(
						'methods'  => WP_REST_Server::CREATABLE,
						'callback' => array( $this, 'save_config' ),
						'permission_callback' => array( $this, 'can_manage' ),
					),
				)
			);

			register_rest_route(
				self::NAMESPACE_SLUG,
				'/model',
				array(
					array(
						'methods'  => WP_REST_Server::READABLE,
						'callback' => array( $this, 'get_model' ),
						'permission_callback' => array( $this, 'can_manage' ),
					),
					array(
						'methods'  => WP_REST_Server::CREATABLE,
						'callback' => array( $this, 'save_model' ),
						'permission_callback' => array( $this, 'can_manage' ),
					),
				)
			);

			register_rest_route(
				self::NAMESPACE_SLUG,
				'/key',
				array(
					array(
						'methods'  => WP_REST_Server::READABLE,
						'callback' => array( $this, 'get_key_status' ),
						'permission_callback' => array( $this, 'can_manage' ),
					),
					array(
						'methods'  => WP_REST_Server::CREATABLE,
						'callback' => array( $this, 'save_key' ),
						'permission_callback' => array( $this, 'can_manage' ),
					),
				)
			);

			register_rest_route(
				self::NAMESPACE_SLUG,
				'/timezone',
				array(
					array(
						'methods'  => WP_REST_Server::READABLE,
						'callback' => array( $this, 'get_timezone' ),
					),
					array(
						'methods'  => WP_REST_Server::CREATABLE,
						'callback' => array( $this, 'save_timezone' ),
						'permission_callback' => array( $this, 'can_manage' ),
					),
				)
			);

			register_rest_route(
				self::NAMESPACE_SLUG,
				'/design',
				array(
					array(
						'methods'  => WP_REST_Server::READABLE,
						'callback' => array( $this, 'get_design_settings' ),
						'permission_callback' => array( $this, 'can_manage' ),
					),
					array(
						'methods'  => WP_REST_Server::CREATABLE,
						'callback' => array( $this, 'save_design_settings' ),
						'permission_callback' => array( $this, 'can_manage' ),
					),
				)
			);

			register_rest_route(
				self::NAMESPACE_SLUG,
				'/vps',
				array(
					'methods'  => WP_REST_Server::READABLE,
					'callback' => array( $this, 'get_vps_collection' ),
					'permission_callback' => array( $this, 'can_manage' ),
				)
			);

			register_rest_route(
				self::NAMESPACE_SLUG,
				'/vps/cached',
				array(
					'methods'  => WP_REST_Server::READABLE,
					'callback' => array( $this, 'get_cached_vps' ),
					'permission_callback' => '__return_true',
				)
			);

			register_rest_route(
				self::NAMESPACE_SLUG,
				'/vps/validate',
				array(
					'methods'  => WP_REST_Server::CREATABLE,
					'callback' => array( $this, 'validate_vps' ),
					'permission_callback' => array( $this, 'can_manage' ),
				)
			);

			register_rest_route(
				self::NAMESPACE_SLUG,
				'/vps/promo',
				array(
					'methods'  => WP_REST_Server::CREATABLE,
					'callback' => array( $this, 'refresh_promo' ),
					'permission_callback' => array( $this, 'can_manage' ),
				)
			);

			register_rest_route(
				self::NAMESPACE_SLUG,
				'/vps/meta',
				array(
					'methods'  => WP_REST_Server::CREATABLE,
					'callback' => array( $this, 'refresh_meta' ),
					'permission_callback' => array( $this, 'can_manage' ),
				)
			);

			register_rest_route(
				self::NAMESPACE_SLUG,
				'/diagnostics',
				array(
					'methods'  => WP_REST_Server::CREATABLE,
					'callback' => array( $this, 'run_diagnostics' ),
					'permission_callback' => array( $this, 'can_manage' ),
				)
			);
		}

		/**
		 * Permission gate.
		 *
		 * @return bool
		 */
		public function can_manage() {
			return current_user_can( 'manage_options' );
		}

		/**
		 * Return config file.
		 *
		 * @return WP_REST_Response
		 */
		public function get_config() {
			return rest_ensure_response(
				array(
					'content' => $this->repository->get_config_raw(),
				)
			);
		}

		/**
		 * Save config file.
		 *
		 * @param WP_REST_Request $request Request.
		 * @return WP_REST_Response
		 */
		public function save_config( $request ) {
			$content = (string) $request->get_param( 'content' );
			$this->repository->save_config_raw( wp_unslash( $content ) );
			return rest_ensure_response( array( 'saved' => true ) );
		}

		/**
		 * Model file.
		 *
		 * @return WP_REST_Response
		 */
		public function get_model() {
			return rest_ensure_response(
				array(
					'content' => $this->repository->get_model_raw(),
				)
			);
		}

		/**
		 * Save model.
		 *
		 * @param WP_REST_Request $request Request.
		 * @return WP_REST_Response
		 */
		public function save_model( $request ) {
			$content = (string) $request->get_param( 'content' );
			$this->repository->save_model_raw( wp_unslash( $content ) );
			return rest_ensure_response( array( 'saved' => true ) );
		}

		/**
		 * API key status.
		 *
		 * @return WP_REST_Response
		 */
		public function get_key_status() {
			return rest_ensure_response(
				array(
					'hasKey' => ! empty( $this->repository->get_api_key() ),
				)
			);
		}

		/**
		 * Save API key.
		 *
		 * @param WP_REST_Request $request Request.
		 * @return WP_REST_Response|WP_Error
		 */
		public function save_key( $request ) {
			$key = sanitize_text_field( (string) $request->get_param( 'api_key' ) );

			if ( empty( $key ) || 0 !== strpos( $key, 'sk-' ) ) {
				return new WP_Error( 'invalid_key', 'API KEY 必须以 sk- 开头。', array( 'status' => 400 ) );
			}

			$this->repository->save_api_key( $key );

			return rest_ensure_response( array( 'saved' => true ) );
		}

		/**
		 * Timezone payload.
		 *
		 * @return WP_REST_Response
		 */
		public function get_timezone() {
			return rest_ensure_response(
				array(
					'timezone' => $this->repository->get_timezone(),
					'options'  => timezone_identifiers_list(),
				)
			);
		}

		/**
		 * Save timezone.
		 *
		 * @param WP_REST_Request $request Request.
		 * @return WP_REST_Response|WP_Error
		 */
		public function save_timezone( $request ) {
			$timezone = (string) $request->get_param( 'timezone' );

			if ( ! in_array( $timezone, timezone_identifiers_list(), true ) ) {
				return new WP_Error( 'invalid_timezone', '非法时区', array( 'status' => 400 ) );
			}

			$this->repository->save_timezone( $timezone );

			return rest_ensure_response( array( 'saved' => true ) );
		}

		/**
		 * Design payload (extra css).
		 *
		 * @return WP_REST_Response
		 */
		public function get_design_settings() {
			return rest_ensure_response(
				array(
					'extraCss' => $this->repository->get_extra_css(),
				)
			);
		}

		/**
		 * Save extra CSS.
		 *
		 * @param WP_REST_Request $request Request.
		 * @return WP_REST_Response
		 */
		public function save_design_settings( $request ) {
			$css = (string) $request->get_param( 'extra_css' );
			$this->repository->save_extra_css( wp_unslash( $css ) );

			return rest_ensure_response( array( 'saved' => true ) );
		}

		/**
		 * Build VPS payload.
		 *
		 * @return WP_REST_Response
		 */
		public function get_vps_collection() {
			$config   = $this->repository->get_config();
			$model    = $this->repository->get_model();
			$statuses = $this->repository->get_statuses();
			$vps      = array();

			foreach ( $this->repository->get_vps_definitions() as $definition ) {
				$meta       = $this->parser->extract_meta( $definition['sale_url'] );
				$status     = isset( $statuses[ $definition['vendor'] . '-' . $definition['pid'] ] ) ? $statuses[ $definition['vendor'] . '-' . $definition['pid'] ] : null;
				$promo      = $this->promo->get_promo( $definition, $model, $meta );
				$meta_view  = $this->meta->get_display( $definition, $model, $meta );
				$vps[]      = array(
					'vendor'        => $definition['vendor'],
					'pid'           => $definition['pid'],
					'sale_url'      => $definition['sale_url'],
					'valid_url'     => $definition['valid_url'],
					'human_comment' => $definition['human_comment'],
					'meta'          => $meta,
					'meta_display'  => isset( $meta_view['content'] ) ? $meta_view['content'] : '',
					'meta_source'   => isset( $meta_view['source'] ) ? $meta_view['source'] : 'raw',
					'promo'         => $promo['content'],
					'promo_source'  => $promo['source'],
					'available'     => $status ? $status['available'] : null,
					'checked_at'    => $status ? $status['checked_at'] : null,
					'message'       => $status ? $status['message'] : '',
					'valid_delay'   => $definition['valid_delay'],
					'interval'      => $definition['interval'],
				);
			}

			$this->repository->save_vps_snapshot( $vps );

			return rest_ensure_response(
				array(
					'vps' => $vps,
				)
			);
		}

		/**
		 * Return cached VPS snapshot without remote fetch.
		 *
		 * @return WP_REST_Response
		 */
		public function get_cached_vps() {
			$snapshot = $this->repository->get_vps_snapshot();
			if ( empty( $snapshot ) ) {
				if ( current_user_can( 'manage_options' ) ) {
					return $this->get_vps_collection();
				}

				return rest_ensure_response( array( 'vps' => array() ) );
			}

			return rest_ensure_response(
				array(
					'vps' => $snapshot,
				)
			);
		}

		/**
		 * Validate VPS.
		 *
		 * @param WP_REST_Request $request Request.
		 * @return WP_REST_Response|WP_Error
		 */
		public function validate_vps( $request ) {
			$vendor = sanitize_text_field( (string) $request->get_param( 'vendor' ) );
			$pid    = sanitize_text_field( (string) $request->get_param( 'pid' ) );

			$definition = $this->find_definition( $vendor, $pid );

			if ( ! $definition ) {
				return new WP_Error( 'not_found', 'VPS 未找到', array( 'status' => 404 ) );
			}

			$model     = $this->repository->get_model();
			$provider  = $this->get_provider_config( $model );
			$result    = $this->availability->validate_vps( $definition, $provider );
			$statuses  = $this->repository->get_statuses();
			$key       = $vendor . '-' . $pid;
			$statuses[ $key ] = array(
				'available' => $result['available'],
				'checked_at' => time(),
				'message'   => $result['message'],
			);
			$this->repository->save_statuses( $statuses );
			$this->repository->update_vps_snapshot_status( $vendor, $pid, $result['available'], $result['message'], $statuses[ $key ]['checked_at'] );

			return rest_ensure_response( $statuses[ $key ] );
		}

		/**
		 * Refresh promo text.
		 *
		 * @param WP_REST_Request $request Request.
		 * @return WP_REST_Response|WP_Error
		 */
		public function refresh_promo( $request ) {
			$vendor  = sanitize_text_field( (string) $request->get_param( 'vendor' ) );
			$pid     = sanitize_text_field( (string) $request->get_param( 'pid' ) );
			$content = $request->get_param( 'content' );

			$definition = $this->find_definition( $vendor, $pid );

			if ( ! $definition ) {
				return new WP_Error( 'not_found', 'VPS 未找到', array( 'status' => 404 ) );
			}

			$model = $this->repository->get_model();
			$meta  = $this->parser->extract_meta( $definition['sale_url'] );

			if ( null !== $content ) {
				$body = sanitize_textarea_field( wp_unslash( (string) $content ) );
				if ( '' === trim( $body ) ) {
					return new WP_Error( 'invalid_promo', '推广语不能为空', array( 'status' => 400 ) );
				}

				$promo = $this->promo->save_manual_promo( $definition, $meta, $body );
				return rest_ensure_response( array(
					'promo'  => $promo['content'],
					'source' => $promo['source'],
				) );
			}

			$this->promo->clear_manual_promo( $definition, $meta );
			$promo = $this->promo->get_promo( $definition, $model, $meta );

			return rest_ensure_response( array(
				'promo'  => $promo['content'],
				'source' => $promo['source'],
			) );
		}

		/**
		 * Format VPS meta info.
		 *
		 * @param WP_REST_Request $request Request.
		 * @return WP_REST_Response|WP_Error
		 */
		public function refresh_meta( $request ) {
			$vendor  = sanitize_text_field( (string) $request->get_param( 'vendor' ) );
			$pid     = sanitize_text_field( (string) $request->get_param( 'pid' ) );
			$content = $request->get_param( 'content' );

			$definition = $this->find_definition( $vendor, $pid );

			if ( ! $definition ) {
				return new WP_Error( 'not_found', 'VPS 未找到', array( 'status' => 404 ) );
			}

			$model = $this->repository->get_model();
			$meta  = $this->parser->extract_meta( $definition['sale_url'] );

			if ( null !== $content ) {
				$body = sanitize_textarea_field( wp_unslash( (string) $content ) );
				if ( '' === trim( $body ) ) {
					return new WP_Error( 'invalid_meta', '展示信息不能为空', array( 'status' => 400 ) );
				}

				$result = $this->meta->save_manual_meta( $definition, $body );
			} else {
				$result = $this->meta->regenerate_meta( $definition, $model, $meta );
			}

			return rest_ensure_response( array(
				'content' => $result['content'],
				'source'  => $result['source'],
			) );
		}

		/**
		 * Diagnostics: network + llm.
		 *
		 * @return WP_REST_Response
		 */
		public function run_diagnostics() {
			$network = wp_remote_get( 'https://www.racknerd.com/', array( 'timeout' => 15 ) );
			$model   = $this->repository->get_model();
			$provider = $this->get_provider_config( $model );
			$llm     = new ASV_LLM_Client(
				isset( $provider['base_url'] ) ? $provider['base_url'] : '',
				$this->repository->get_api_key(),
				isset( $provider['model'] ) ? $provider['model'] : 'gpt-4.1-mini'
			);

			$llm_status = array( 'ok' => false, 'message' => 'LLM 未配置' );
			if ( $llm->is_ready() ) {
				$result = $llm->request( '你是一个诊断助手', '请回复 TRUE 确认你可以正常工作。' );
				$llm_status = array(
					'ok'      => $result['success'] && false !== strpos( strtoupper( $result['content'] ?? '' ), 'TRUE' ),
					'message' => $result['success'] ? $result['content'] : $result['message'],
				);
			}

			return rest_ensure_response(
				array(
					'network' => array(
						'ok'      => ! is_wp_error( $network ) && 200 === (int) wp_remote_retrieve_response_code( $network ),
						'message' => is_wp_error( $network ) ? $network->get_error_message() : '连接正常',
					),
					'llm' => $llm_status,
				)
			);
		}

		/**
		 * Find definition.
		 *
		 * @param string $vendor Vendor.
		 * @param string $pid    Pid.
		 * @return array|null
		 */
		protected function find_definition( $vendor, $pid ) {
			foreach ( $this->repository->get_vps_definitions() as $definition ) {
				if ( $definition['vendor'] === $vendor && $definition['pid'] === $pid ) {
					return $definition;
				}
			}

			return null;
		}

		/**
		 * Provider config helper.
		 *
		 * @param array $model Model tree.
		 * @return array
		 */
		protected function get_provider_config( $model ) {
			if ( isset( $model['model_providers'] ) && is_array( $model['model_providers'] ) ) {
				foreach ( $model['model_providers'] as $provider ) {
					return is_array( $provider ) ? $provider : array();
				}
			}

			return array();
		}
	}
}
