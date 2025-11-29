<?php
/**
 * Meta formatting helper.
 *
 * @package AutoSaleVPS
 */

require_once __DIR__ . '/class-asv-llm-client.php';

if ( ! class_exists( 'ASV_Meta_Service' ) ) {
	class ASV_Meta_Service {
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
		 * Resolve formatted meta lines for display.
		 *
		 * @param array $vps   Vps definition.
		 * @param array $model Model configuration.
		 * @param array $meta  Raw meta.
		 * @return array
		 */
		public function get_display( $vps, $model, $meta ) {
			$manual = $this->repository->get_meta_override( $vps['vendor'], $vps['pid'] );
			if ( '' !== $manual ) {
				return array(
					'content' => $this->normalize_text( $manual ),
					'source'  => 'manual',
				);
			}

			$key   = $this->build_cache_key( $vps, $meta );
			$cache = $this->repository->get_meta_cache();
			if ( isset( $cache[ $key ]['content'] ) ) {
				return array(
					'content' => (string) $cache[ $key ]['content'],
					'source'  => isset( $cache[ $key ]['source'] ) ? $cache[ $key ]['source'] : 'raw',
				);
			}

			return $this->generate_display( $vps, $model, $meta, $key, $cache );
		}

		/**
		 * Persist manual override.
		 *
		 * @param array  $vps     Vps definition.
		 * @param string $content Manual text.
		 * @return array
		 */
		public function save_manual_meta( $vps, $content ) {
			$body = $this->normalize_text( $content );
			$this->repository->save_meta_override( $vps['vendor'], $vps['pid'], $body );
			$this->purge_cache_for( $vps['vendor'], $vps['pid'] );
			return array(
				'content' => $body,
				'source'  => 'manual',
			);
		}

		/**
		 * Force re-generation via AI/fallback.
		 *
		 * @param array $vps   Vps definition.
		 * @param array $model Model configuration.
		 * @param array $meta  Raw meta lines.
		 * @return array
		 */
		public function regenerate_meta( $vps, $model, $meta ) {
			$this->repository->save_meta_override( $vps['vendor'], $vps['pid'], '' );
			$this->purge_cache_for( $vps['vendor'], $vps['pid'] );
			$key   = $this->build_cache_key( $vps, $meta );
			$cache = $this->repository->get_meta_cache();
			return $this->generate_display( $vps, $model, $meta, $key, $cache );
		}

		/**
		 * Generate formatted meta text.
		 *
		 * @param array  $vps   Vps definition.
		 * @param array  $model Model configuration.
		 * @param array  $meta  Raw meta lines.
		 * @param string $key   Cache key.
		 * @param array  $cache Cache pool.
		 * @return array
		 */
		protected function generate_display( $vps, $model, $meta, $key, $cache ) {
			$provider = $this->get_provider_config( $model );
			$prompt   = isset( $provider['prompt_meta_layout'] ) ? (string) $provider['prompt_meta_layout'] : '';
			$llm      = new ASV_LLM_Client(
				isset( $provider['base_url'] ) ? $provider['base_url'] : '',
				$this->repository->get_api_key(),
				isset( $provider['model'] ) ? $provider['model'] : 'gpt-4o-mini'
			);

			$content = $this->format_raw_meta( $meta );
			$source  = 'raw';

			if ( $llm->is_ready() && ! empty( $prompt ) ) {
				$payload = array(
					'Vendor'       => strtoupper( $vps['vendor'] ),
					'Pid'          => $vps['pid'],
					'HumanComment' => isset( $vps['human_comment'] ) ? $vps['human_comment'] : '',
					'Meta'         => array_values( array_filter( $meta ) ),
				);
				$context = '请将 JSON 中的 VPS 元信息整理成多行中文摘要，突出重点配置，每行不超过30个字，语气客观易读。' . "\n" . wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
				$result  = $llm->request( $prompt, $context );
				if ( $result['success'] && ! empty( $result['content'] ) ) {
					$content = $this->normalize_text( $result['content'] );
					$source  = 'ai';
				}
			}

			$cache[ $key ] = array(
				'content' => $content,
				'source'  => $source,
				'updated' => time(),
				'vendor'  => $vps['vendor'],
				'pid'     => $vps['pid'],
			);
			$this->repository->save_meta_cache( $cache );

			return array(
				'content' => $content,
				'source'  => $source,
			);
		}

		/**
		 * Format fallback content.
		 *
		 * @param array $meta Raw meta.
		 * @return string
		 */
		protected function format_raw_meta( $meta ) {
			$lines = array_values( array_filter( array_map( 'trim', $meta ) ) );
			if ( empty( $lines ) ) {
				return '等待抓取 VPS 详情，保存配置并验证后自动填充。';
			}

			return implode( "\n", $lines );
		}

		/**
		 * Normalize text into clean multiline content.
		 *
		 * @param string $text Raw text.
		 * @return string
		 */
		protected function normalize_text( $text ) {
			$normalized = preg_replace( "/\r\n?/", "\n", (string) $text );
			$parts      = array_map( 'trim', explode( "\n", $normalized ) );
			$parts      = array_filter( $parts, 'strlen' );
			return implode( "\n", $parts );
		}

		/**
		 * Remove cached entries for a VPS.
		 *
		 * @param string $vendor Vendor key.
		 * @param string $pid    Product id.
		 */
		protected function purge_cache_for( $vendor, $pid ) {
			$cache   = $this->repository->get_meta_cache();
			$changed = false;
			foreach ( $cache as $key => $entry ) {
				if ( isset( $entry['vendor'], $entry['pid'] ) && $entry['vendor'] === $vendor && $entry['pid'] === $pid ) {
					unset( $cache[ $key ] );
					$changed = true;
				}
			}

			if ( $changed ) {
				$this->repository->save_meta_cache( $cache );
			}
		}

		/**
		 * Cache key per VPS/meta tuple.
		 *
		 * @param array $vps  Vps definition.
		 * @param array $meta Raw meta.
		 * @return string
		 */
		protected function build_cache_key( $vps, $meta ) {
			return sha1( wp_json_encode( array(
				'vendor' => $vps['vendor'],
				'pid'    => $vps['pid'],
				'meta'   => array_values( $meta ),
			) ) );
		}

		/**
		 * Pick provider configuration.
		 *
		 * @param array $model Model config.
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
