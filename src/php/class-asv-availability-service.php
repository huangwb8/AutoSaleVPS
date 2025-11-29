<?php
/**
 * Availability checks.
 *
 * @package AutoSaleVPS
 */

require_once __DIR__ . '/class-asv-llm-client.php';

if ( ! class_exists( 'ASV_Availability_Service' ) ) {
	class ASV_Availability_Service {
		/**
		 * Config repo.
		 *
		 * @var ASV_Config_Repository
		 */
		protected $repository;

		/**
		 * Constructor.
		 *
		 * @param ASV_Config_Repository $repository Repository.
		 */
		public function __construct( ASV_Config_Repository $repository ) {
			$this->repository = $repository;
		}

		/**
		 * Validate single VPS.
		 *
		 * @param array $vps     VPS definition.
		 * @param array $prompts Prompt config.
		 * @return array
		 */
		public function validate_vps( $vps, $provider ) {
			$valid_url = isset( $vps['valid_url'] ) ? $vps['valid_url'] : '';
			if ( empty( $valid_url ) ) {
				return array(
					'available' => false,
					'message'   => 'Missing valid URL',
				);
			}

			$response = wp_remote_get( $valid_url, array( 'timeout' => 20 ) );

			if ( is_wp_error( $response ) ) {
				return array(
					'available' => false,
					'message'   => $response->get_error_message(),
				);
			}

			$body   = wp_remote_retrieve_body( $response );
			$reason = 'Unknown response';
			$state  = $this->basic_scan( $body );

			if ( 'unknown' === $state ) {
				$prompt = isset( $provider['prompt_valid'] ) ? $provider['prompt_valid'] : '';
				$llm    = $this->build_llm_client( $provider );
				if ( $llm->is_ready() && ! empty( $prompt ) ) {
					$result = $llm->request( $prompt, (string) $body );
					if ( $result['success'] ) {
						$reason = $result['content'];
						$state  = $this->interpret_llm( $result['content'] );
					} else {
						$reason = $result['message'];
					}
				} else {
					$reason = 'LLM disabled, using heuristic.';
				}
			}

			return array(
				'available' => 'online' === $state,
				'message'   => $reason,
				'raw'       => $body,
			);
		}

		/**
		 * Crude heuristics.
		 *
		 * @param string $body Response body.
		 * @return string online|offline|unknown
		 */
		protected function basic_scan( $body ) {
			$haystack = strtolower( (string) $body );
			$offline  = array( 'out of stock', 'sold out', 'problem', 'oops', 'unavailable' );
			foreach ( $offline as $needle ) {
				if ( false !== strpos( $haystack, $needle ) ) {
					return 'offline';
				}
			}

			if ( ! empty( $haystack ) ) {
				return 'unknown';
			}

			return 'offline';
		}

		/**
		 * Interpret LLM response.
		 *
		 * @param string $content Response text.
		 * @return string
		 */
		protected function interpret_llm( $content ) {
			$content = strtoupper( trim( $content ) );
			if ( false !== strpos( $content, 'FALSE' ) ) {
				return 'offline';
			}

			if ( false !== strpos( $content, 'TRUE' ) ) {
				return 'online';
			}

			return 'unknown';
		}

		/**
		 * Build llm client.
		 *
		 * @param array $prompts Model config.
		 * @return ASV_LLM_Client
		 */
		protected function build_llm_client( $provider ) {
			$api_key  = $this->repository->get_api_key();
			$base_url = isset( $provider['base_url'] ) ? $provider['base_url'] : '';
			$model    = isset( $provider['model'] ) ? $provider['model'] : 'gpt-4o-mini';

			return new ASV_LLM_Client( $base_url, $api_key, $model );
		}
	}
}
