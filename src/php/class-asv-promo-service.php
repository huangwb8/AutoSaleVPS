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
		 * Repository handler.
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
		 * @return array
		 */
		public function get_promo( $vps, $model, $meta ) {
			$manual = $this->repository->get_promo_override( $vps['vendor'], $vps['pid'] );
			if ( '' !== $manual ) {
				return array(
					'content' => $manual,
					'source'  => 'manual',
				);
			}

			$key     = $this->build_cache_key( $vps, $meta );
			$cache   = $this->repository->get_promo_cache();
			$cached  = isset( $cache[ $key ] ) ? $cache[ $key ] : null;
			$comment = isset( $vps['human_comment'] ) ? $vps['human_comment'] : '';

			if ( $cached && isset( $cached['content'] ) ) {
				return array(
					'content' => $cached['content'],
					'source'  => isset( $cached['source'] ) ? $cached['source'] : 'llm',
				);
			}

			$provider = $this->get_provider_config( $model );
			$prompt   = isset( $provider['prompt_vps_info'] ) ? $provider['prompt_vps_info'] : '';
			$llm      = new ASV_LLM_Client(
				isset( $provider['base_url'] ) ? $provider['base_url'] : '',
				$this->repository->get_api_key(),
				isset( $provider['model'] ) ? $provider['model'] : 'gpt-4o-mini'
			);

			$statement = '模型尚未配置';
			$source    = 'fallback';
			$payload   = array(
				'Vendor'       => strtoupper( $vps['vendor'] ),
				'Pid'          => $vps['pid'],
				'SaleUrl'      => isset( $vps['sale_url'] ) ? $vps['sale_url'] : '',
				'ValidUrl'     => isset( $vps['valid_url'] ) ? $vps['valid_url'] : '',
				'HumanComment' => $comment,
				'Meta'         => array_values( array_filter( $meta ) ),
			);
			$context = '请阅读 JSON 中的 VPS 数据，并基于这些事实生成 20-100 字的中文推广语，语气自然专业。' . "\n" . wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

			if ( $llm->is_ready() && ! empty( $prompt ) ) {
				$result = $llm->request( $prompt, $context );
				if ( $result['success'] ) {
					$statement = $result['content'];
					$source    = 'llm';
				} else {
					$statement = 'LLM错误: ' . $result['message'];
				}
			} else {
				$statement = sprintf( '%s #%s：%s', strtoupper( $vps['vendor'] ), $vps['pid'], $comment ? $comment : '这款VPS配置紧凑，适合入门。' );
			}

			$cache[ $key ] = array(
				'content' => $statement,
				'updated' => time(),
				'source'  => $source,
			);
			$this->repository->save_promo_cache( $cache );

			return array(
				'content' => $statement,
				'source'  => $source,
			);
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
		 * Save manual promo and clear caches.
		 *
		 * @param array  $vps     Vps item.
		 * @param array  $meta    Meta lines.
		 * @param string $content Manual content.
		 * @return array
		 */
		public function save_manual_promo( $vps, $meta, $content ) {
			$this->repository->save_promo_override( $vps['vendor'], $vps['pid'], $content );
			$this->invalidate( $vps, $meta );
			return array(
				'content' => $content,
				'source'  => 'manual',
			);
		}

		/**
		 * Clear manual promo and cache.
		 *
		 * @param array $vps  Vps item.
		 * @param array $meta Meta lines.
		 */
		public function clear_manual_promo( $vps, $meta ) {
			$this->repository->save_promo_override( $vps['vendor'], $vps['pid'], '' );
			$this->invalidate( $vps, $meta );
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
