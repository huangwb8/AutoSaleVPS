<?php
/**
 * Promo generation helper.
 *
 * @package AutoSaleVPS
 */

require_once __DIR__ . '/class-asv-llm-client.php';

if ( ! class_exists( 'ASV_Promo_Service' ) ) {
	class ASV_Promo_Service {
		/**
		 * Repository.
		 *
		 * @var ASV_Config_Repository
		 */
		protected $repository;

		/**
		 * Constructor.
		 *
		 * @param ASV_Config_Repository $repository Repo.
		 */
		public function __construct( ASV_Config_Repository $repository ) {
			$this->repository = $repository;
		}

		/**
		 * Generate or return cached promo.
		 *
		 * @param array $vps    Vps definition.
		 * @param array $model  Model data.
		 * @param array $meta   Meta lines.
		 * @return string
		 */
		public function get_promo( $vps, $model, $meta ) {
			$key     = $this->build_cache_key( $vps, $meta );
			$cache   = $this->repository->get_promo_cache();
			$cached  = isset( $cache[ $key ] ) ? $cache[ $key ] : null;
			$comment = isset( $vps['human_comment'] ) ? $vps['human_comment'] : '';

			if ( $cached && isset( $cached['content'] ) ) {
				return $cached['content'];
			}

			$provider = $this->get_provider_config( $model );
			$prompt   = isset( $provider['prompt_vps_info'] ) ? $provider['prompt_vps_info'] : '';
			$llm      = new ASV_LLM_Client(
				isset( $provider['base_url'] ) ? $provider['base_url'] : '',
				$this->repository->get_api_key(),
				isset( $provider['model'] ) ? $provider['model'] : 'gpt-4o-mini'
			);

			$context   = sprintf( "VENDOR: %s PID: %s\\nCOMMENT: %s\\nDETAILS: %s", $vps['vendor'], $vps['pid'], $comment, implode( '; ', $meta ) );
			$statement = '模型尚未配置';

			if ( $llm->is_ready() && ! empty( $prompt ) ) {
				$result = $llm->request( $prompt, $context );
				if ( $result['success'] ) {
					$statement = $result['content'];
				} else {
					$statement = 'LLM错误: ' . $result['message'];
				}
			} else {
				$statement = sprintf( '%s #%s：%s', strtoupper( $vps['vendor'] ), $vps['pid'], $comment ? $comment : '这款VPS配置紧凑，适合入门。' );
			}

			$cache[ $key ] = array(
				'content' => $statement,
				'updated' => time(),
			);
			$this->repository->save_promo_cache( $cache );

			return $statement;
		}

		/**
		 * Remove cached promo.
		 *
		 * @param array $vps  Vps item.
		 * @param array $meta Meta.
		 */
		public function invalidate( $vps, $meta ) {
			$cache = $this->repository->get_promo_cache();
			$key   = $this->build_cache_key( $vps, $meta );
			if ( isset( $cache[ $key ] ) ) {
				unset( $cache[ $key ] );
				$this->repository->save_promo_cache( $cache );
			}
		}

		/**
		 * Cache key.
		 *
		 * @param array $vps  Vps data.
		 * @param array $meta Meta lines.
		 * @return string
		 */
		protected function build_cache_key( $vps, $meta ) {
			$payload = wp_json_encode( array(
				'vendor' => $vps['vendor'],
				'pid'    => $vps['pid'],
				'meta'   => $meta,
				'comment'=> isset( $vps['human_comment'] ) ? $vps['human_comment'] : '',
			) );

			return sha1( $payload );
		}

		/**
		 * Derive provider config.
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
