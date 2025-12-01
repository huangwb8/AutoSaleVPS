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

			$body        = wp_remote_retrieve_body( $response );
			$status_code = (int) wp_remote_retrieve_response_code( $response );
			$headers     = $this->collect_headers( $response );
			$scan        = $this->basic_scan( $body );
			$state       = isset( $scan['state'] ) ? $scan['state'] : 'unknown';
			$reason      = isset( $scan['reason'] ) && ! empty( $scan['reason'] ) ? $scan['reason'] : 'Waiting for analysis';
			$llm         = $this->build_llm_client( $provider );
			$prompt      = $this->build_llm_prompt( $provider );

			if ( $llm->is_ready() && ! empty( $prompt ) ) {
				$payload = $this->build_llm_payload( $vps, $status_code, $headers, $body, $scan );
				$result  = $llm->request( $prompt, $payload );
				if ( $result['success'] ) {
					$analysis = $this->interpret_llm( $result['content'] );
					if ( ! empty( $analysis['state'] ) && 'unknown' !== $analysis['state'] ) {
						$state = $analysis['state'];
					}
					if ( ! empty( $analysis['reason'] ) ) {
						$reason = $analysis['reason'];
					} else {
						$reason = $result['content'];
					}
				} else {
					$reason = $result['message'];
				}
			} elseif ( 'unknown' === $state ) {
				$reason = 'LLM disabled, using heuristic.';
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
			$signals  = $this->extract_signals_from_text( $haystack );
			foreach ( $signals as $signal ) {
				if ( 'offline' === $signal['label'] ) {
					return array(
						'state'   => 'offline',
						'reason'  => $signal['message'],
						'signals' => $signals,
					);
				}
			}

			foreach ( $signals as $signal ) {
				if ( 'online' === $signal['label'] ) {
					return array(
						'state'   => 'online',
						'reason'  => $signal['message'],
						'signals' => $signals,
					);
				}
			}

			if ( empty( trim( $haystack ) ) ) {
				return array(
					'state'   => 'offline',
					'reason'  => 'Empty page body',
					'signals' => $signals,
				);
			}

			return array(
				'state'   => 'unknown',
				'reason'  => '',
				'signals' => $signals,
			);
		}

		/**
		 * Interpret LLM response.
		 *
		 * @param string $content Response text.
		 * @return string
		 */
		protected function interpret_llm( $content ) {
			$decoded = $this->decode_json_block( $content );
			$reason  = trim( (string) $content );
			if ( is_array( $decoded ) ) {
				$status = isset( $decoded['status'] ) ? $this->normalize_status_label( $decoded['status'] ) : 'unknown';
				if ( isset( $decoded['reason'] ) && '' !== trim( (string) $decoded['reason'] ) ) {
					$reason = trim( (string) $decoded['reason'] );
				}

				return array(
					'state'      => $status,
					'reason'     => $reason,
					'confidence' => isset( $decoded['confidence'] ) ? (float) $decoded['confidence'] : null,
				);
			}

			$content = strtoupper( $reason );
			if ( false !== strpos( $content, 'FALSE' ) ) {
				return array(
					'state'  => 'offline',
					'reason' => trim( (string) $reason ),
				);
			}

			if ( false !== strpos( $content, 'TRUE' ) ) {
				return array(
					'state'  => 'online',
					'reason' => trim( (string) $reason ),
				);
			}

			return array(
				'state'  => 'unknown',
				'reason' => trim( (string) $reason ),
			);
		}

		/**
		 * Build prompt for LLM.
		 *
		 * @param array $provider Provider config.
		 * @return string
		 */
		protected function build_llm_prompt( $provider ) {
			$base_prompt = '你是一名VPS可卖性监控助手。你会收到一个JSON，里面包含厂商、PID、HTTP状态、页面摘要以及启发式信号。请结合所有信息判断该VPS是否还能下单。输出必须是JSON，字段如下：status("online"|"offline"|"unknown"), reason(<=120字), confidence(0-1之间的小数，可选)，evidence(可选数组，列出关键信息)。严禁输出除JSON以外的文字。';
			$extra       = isset( $provider['prompt_valid'] ) ? trim( (string) $provider['prompt_valid'] ) : '';
			if ( ! empty( $extra ) ) {
				$base_prompt .= '\n\n决策要求：' . $extra . '\n务必保持JSON格式。';
			}

			return $base_prompt;
		}

		/**
		 * Build payload JSON for LLM analysis.
		 *
		 * @param array  $vps         VPS definition.
		 * @param int    $status_code HTTP status.
		 * @param array  $headers     Trimmed headers.
		 * @param string $body        Raw body.
		 * @param array  $scan        Heuristic scan result.
		 * @return string
		 */
		protected function build_llm_payload( $vps, $status_code, $headers, $body, $scan ) {
			$payload = array(
				'vendor'          => isset( $vps['vendor'] ) ? (string) $vps['vendor'] : '',
				'pid'             => isset( $vps['pid'] ) ? (string) $vps['pid'] : '',
				'valid_url'       => isset( $vps['valid_url'] ) ? (string) $vps['valid_url'] : '',
				'status_code'     => $status_code,
				'headers'         => $headers,
				'initial_state'   => isset( $scan['state'] ) ? $scan['state'] : 'unknown',
				'initial_reason'  => isset( $scan['reason'] ) ? $scan['reason'] : '',
				'signals'         => isset( $scan['signals'] ) ? $scan['signals'] : array(),
				'content_preview' => $this->summarize_body( $body ),
				'html_excerpt'    => $this->truncate_text( (string) $body, 800 ),
			);

			return function_exists( 'wp_json_encode' ) ? wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) : json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		}

		/**
		 * Collect useful headers for LLM.
		 *
		 * @param array|WP_Error $response HTTP response.
		 * @return array
		 */
		protected function collect_headers( $response ) {
			$raw     = wp_remote_retrieve_headers( $response );
			$headers = array();
			if ( is_object( $raw ) && method_exists( $raw, 'getAll' ) ) {
				$headers = $raw->getAll();
			} else {
				$headers = (array) $raw;
			}

			$useful = array();
			foreach ( array( 'content-type', 'cf-cache-status', 'server' ) as $key ) {
				if ( isset( $headers[ $key ] ) ) {
					$useful[ $key ] = $headers[ $key ];
				}
			}

			return $useful;
		}

		/**
		 * Summarize page body.
		 *
		 * @param string $body Raw body.
		 * @return string
		 */
		protected function summarize_body( $body ) {
			$text = function_exists( 'wp_strip_all_tags' ) ? wp_strip_all_tags( (string) $body ) : strip_tags( (string) $body );
			$text = preg_replace( '/\s+/u', ' ', $text );
			return $this->truncate_text( trim( (string) $text ), 1000 );
		}

		/**
		 * Truncate helper that supports multibyte strings.
		 *
		 * @param string $text  Text to shorten.
		 * @param int    $limit Limit.
		 * @return string
		 */
		protected function truncate_text( $text, $limit ) {
			$text = (string) $text;
			if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) ) {
				if ( mb_strlen( $text ) <= $limit ) {
					return $text;
				}

				return rtrim( mb_substr( $text, 0, $limit ), "\x00..\x1F" ) . '...';
			}

			if ( strlen( $text ) <= $limit ) {
				return $text;
			}

			return rtrim( substr( $text, 0, $limit ), "\x00..\x1F" ) . '...';
		}

		/**
		 * Extract keyword signals.
		 *
		 * @param string $haystack Lowercase body.
		 * @return array
		 */
		protected function extract_signals_from_text( $haystack ) {
			$signals      = array();
			$offline_map  = array(
				'out of stock'          => '出现“out of stock”字样',
				'sold out'               => '出现“sold out”字样',
				'currently unavailable'  => '出现“currently unavailable”提示',
				'not available'          => '出现“not available”提示',
				'discontinued'           => '页面提示已下架',
				'oops'                   => '页面出现错误提示',
			);
			$online_map   = array(
				'add to cart'   => '包含“add to cart”，疑似可下单',
				'in stock'      => '包含“in stock”，疑似有货',
				'order now'     => '包含“order now”，疑似可售',
			);

			foreach ( $offline_map as $needle => $message ) {
				if ( false !== strpos( $haystack, $needle ) ) {
					$signals[] = array(
						'phrase' => $needle,
						'label'  => 'offline',
						'message' => $message,
					);
				}
			}

			foreach ( $online_map as $needle => $message ) {
				if ( false !== strpos( $haystack, $needle ) ) {
					$signals[] = array(
						'phrase' => $needle,
						'label'  => 'online',
						'message' => $message,
					);
				}
			}

			return $signals;
		}

		/**
		 * Decode json content from LLM output.
		 *
		 * @param string $content Raw content.
		 * @return array|null
		 */
		protected function decode_json_block( $content ) {
			$content = trim( (string) $content );
			$data    = json_decode( $content, true );
			if ( JSON_ERROR_NONE === json_last_error() && is_array( $data ) ) {
				return $data;
			}

			$start = strpos( $content, '{' );
			$end   = strrpos( $content, '}' );
			if ( false !== $start && false !== $end && $end > $start ) {
				$snippet = substr( $content, $start, $end - $start + 1 );
				$data    = json_decode( $snippet, true );
				if ( JSON_ERROR_NONE === json_last_error() && is_array( $data ) ) {
					return $data;
				}
			}

			return null;
		}

		/**
		 * Normalize status label from model response.
		 *
		 * @param string $status Raw status.
		 * @return string
		 */
		protected function normalize_status_label( $status ) {
			$status = strtolower( trim( (string) $status ) );
			if ( in_array( $status, array( 'online', 'available', 'true', 'ok' ), true ) ) {
				return 'online';
			}

			if ( in_array( $status, array( 'offline', 'soldout', 'false', 'sold_out', 'out', 'error' ), true ) ) {
				return 'offline';
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
			$model    = isset( $provider['model'] ) ? $provider['model'] : 'gpt-4.1-mini';

			return new ASV_LLM_Client( $base_url, $api_key, $model );
		}
	}
}
