<?php
/**
 * Lightweight LLM helper.
 *
 * @package AutoSaleVPS
 */

if ( ! class_exists( 'ASV_LLM_Client' ) ) {
	class ASV_LLM_Client {
		/**
		 * Base URL.
		 *
		 * @var string
		 */
		protected $base_url;

		/**
		 * API key.
		 *
		 * @var string
		 */
		protected $api_key;

		/**
		 * Model identifier.
		 *
		 * @var string
		 */
		protected $model;

		/**
		 * Constructor.
		 *
		 * @param string $base_url Provider url.
		 * @param string $api_key  Key.
		 * @param string $model    Model.
		 */
		public function __construct( $base_url, $api_key, $model = 'gpt-4.1-mini' ) {
			$this->base_url = untrailingslashit( $base_url );
			$this->api_key  = (string) $api_key;
			$this->model    = $model;
		}

		/**
		 * Determine if client ready.
		 *
		 * @return bool
		 */
		public function is_ready() {
			return ! empty( $this->base_url ) && ! empty( $this->api_key );
		}

		/**
		 * Request chat completion.
		 *
		 * @param string $system_prompt System prompt.
		 * @param string $user_content  User message.
		 * @return array
		 */
		public function request( $system_prompt, $user_content ) {
			if ( ! $this->is_ready() ) {
				return array(
					'success' => false,
					'message' => 'Missing API configuration.',
				);
			}

			$endpoint = trailingslashit( $this->base_url ) . 'chat/completions';
			$args     = array(
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $this->api_key,
				),
				'body'    => wp_json_encode(
					array(
						'model'    => $this->model,
						'messages' => array(
							array(
								'role'    => 'system',
								'content' => $system_prompt,
							),
							array(
								'role'    => 'user',
								'content' => $user_content,
							),
						),
					)
				),
				'timeout' => 20,
			);

			$response = wp_remote_post( $endpoint, $args );

			if ( is_wp_error( $response ) ) {
				return array(
					'success' => false,
					'message' => $response->get_error_message(),
				);
			}

			$code = wp_remote_retrieve_response_code( $response );
			$body = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( 200 !== (int) $code || empty( $body['choices'][0]['message']['content'] ) ) {
				return array(
					'success' => false,
					'message' => 'Unexpected response from model provider.',
				);
			}

			return array(
				'success' => true,
				'content' => trim( (string) $body['choices'][0]['message']['content'] ),
			);
		}
	}
}
