<?php
/**
 * OpenAI shared functionality
 */

namespace Classifai\Providers\OpenAI;

use Classifai\Providers\OpenAI\APIRequest;
use WP_Error;

trait OpenAI {

	/**
	 * OpenAI completions URL
	 *
	 * @var string
	 */
	protected $compeletions_url = 'https://api.openai.com/v1/completions';

	/**
	 * Sanitize the API key, showing an error message if needed.
	 *
	 * @param array $new_settings New settings being saved.
	 * @param array $old_settings Existing settings, if any.
	 * @return array
	 */
	protected function sanitize_api_key_settings( array $new_settings = [], array $old_settings = [] ) {
		$authenticated = $this->authenticate_credentials( $old_settings['api_key'] ?? '' );

		if ( is_wp_error( $authenticated ) ) {
			$new_settings['authenticated'] = false;
			$error_message                 = $authenticated->get_error_message();

			// For response code 429, credentials are valid but rate limit is reached.
			if ( 429 === (int) $authenticated->get_error_code() ) {
				$new_settings['authenticated'] = true;
				$error_message                 = str_replace( 'plan and billing details', '<a href="https://platform.openai.com/account/billing/overview" target="_blank" rel="noopener">plan and billing details</a>', $error_message );
			} else {
				$error_message = str_replace( 'https://platform.openai.com/account/api-keys', '<a href="https://platform.openai.com/account/api-keys" target="_blank" rel="noopener">https://platform.openai.com/account/api-keys</a>', $error_message );
			}

			add_settings_error(
				'api_key',
				'classifai-auth',
				$error_message,
				'error'
			);
		} else {
			$new_settings['authenticated'] = true;
		}

		$new_settings['api_key'] = sanitize_text_field( $old_settings['api_key'] ?? '' );

		return $new_settings;
	}

	/**
	 * Authenticate our credentials.
	 *
	 * @param string $api_key Api Key.
	 * @return bool|WP_Error
	 */
	protected function authenticate_credentials( string $api_key = '' ) {
		// Check that we have credentials before hitting the API.
		if ( empty( $api_key ) ) {
			return new WP_Error( 'auth', esc_html__( 'Please enter your OpenAI API key.', 'classifai' ) );
		}

		// Make request to ensure credentials work.
		$request  = new APIRequest( $api_key );
		$response = $request->post(
			$this->compeletions_url,
			[
				'body' => wp_json_encode(
					[
						'model'      => 'ada',
						'prompt'     => 'hi',
						'max_tokens' => 1,
					]
				),
			]
		);

		return ! is_wp_error( $response ) ? true : $response;
	}

	/**
	 * Format the result of most recent request.
	 *
	 * @param string $transient Transient that holds our data.
	 * @return string
	 */
	private function get_formatted_latest_response( string $transient = '' ) {
		$data = get_transient( $transient );

		if ( ! $data ) {
			return __( 'N/A', 'classifai' );
		}

		if ( is_wp_error( $data ) ) {
			return $data->get_error_message();
		}

		return preg_replace( '/,"/', ', "', wp_json_encode( $data ) );
	}

}
