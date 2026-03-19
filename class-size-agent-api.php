<?php
/**
 * External API integration.
 *
 * @package SizeAgent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * API class.
 */
class Size_Agent_API {

	/**
	 * Settings object.
	 *
	 * @var Size_Agent_Settings
	 */
	protected $settings;

	/**
	 * Constructor.
	 *
	 * @param Size_Agent_Settings $settings Settings instance.
	 */
	public function __construct( $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Send payload to external API.
	 *
	 * NOTE: Customize endpoint path, auth headers, payload fields and response mapping here
	 * to match your external service contract.
	 *
	 * @param array $payload Request payload.
	 * @return array|WP_Error
	 */
	public function request_size_recommendation( $payload ) {
		if ( (int) $this->settings->get( 'test_mode' ) === 1 ) {
			$this->maybe_log_debug(
				'Test mode response',
				array(
					'product_id' => isset( $payload['product']['id'] ) ? absint( $payload['product']['id'] ) : 0,
				)
			);

			return array(
				'recommended_size' => 'Medium',
				'confidence'       => 'High',
				'fit_note'         => 'Test mode response',
			);
		}

		$base_url = untrailingslashit( (string) $this->settings->get( 'api_base_url' ) );
		$api_key  = (string) $this->settings->get( 'api_key' );

		if ( empty( $base_url ) || empty( $api_key ) ) {
			return new WP_Error( 'size_agent_missing_settings', __( 'Size service is not configured yet. Please contact support.', 'size-agent' ) );
		}

		$endpoint = $base_url . '/recommendations';
		$args     = array(
			'timeout' => 15,
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $api_key,
			),
			'body'    => wp_json_encode( $payload ),
		);

		$this->maybe_log_debug(
			'API request summary',
			array(
				'endpoint'    => $endpoint,
				'product_id'  => isset( $payload['product']['id'] ) ? absint( $payload['product']['id'] ) : 0,
				'has_user'    => isset( $payload['user'] ) && is_array( $payload['user'] ),
			)
		);

		$response = wp_remote_post( $endpoint, $args );

		if ( is_wp_error( $response ) ) {
			$this->maybe_log_debug( 'API transport error', array( 'error' => $response->get_error_message() ) );
			return new WP_Error( 'size_agent_api_unavailable', __( 'Unable to contact the size service right now. Please try again.', 'size-agent' ) );
		}

		$status       = (int) wp_remote_retrieve_response_code( $response );
		$raw_body     = (string) wp_remote_retrieve_body( $response );
		$content_type = (string) wp_remote_retrieve_header( $response, 'content-type' );
		$data         = json_decode( $raw_body, true );

		$this->maybe_log_debug(
			'API response summary',
			array(
				'status'        => $status,
				'content_type'  => $content_type,
				'json_decoded'  => is_array( $data ),
			)
		);

		if ( $status < 200 || $status >= 300 ) {
			$clean_message = __( 'Size recommendation could not be generated at the moment.', 'size-agent' );
			if ( is_array( $data ) && ! empty( $data['message'] ) ) {
				$clean_message = sanitize_text_field( (string) $data['message'] );
			}
			return new WP_Error( 'size_agent_api_error', $clean_message );
		}

		if ( '' === $raw_body || ! is_array( $data ) ) {
			return new WP_Error( 'size_agent_invalid_response', __( 'Received an invalid response from the size service.', 'size-agent' ) );
		}

		$recommended_size = isset( $data['recommended_size'] ) ? sanitize_text_field( (string) $data['recommended_size'] ) : '';
		$confidence       = isset( $data['confidence'] ) ? sanitize_text_field( (string) $data['confidence'] ) : '';
		$fit_note         = isset( $data['fit_note'] ) ? sanitize_textarea_field( (string) $data['fit_note'] ) : '';

		if ( '' === $recommended_size ) {
			return new WP_Error( 'size_agent_missing_fields', __( 'Size service response was incomplete. Please try again.', 'size-agent' ) );
		}

		return array(
			'recommended_size' => $recommended_size,
			'confidence'       => $confidence,
			'fit_note'         => $fit_note,
		);
	}

	/**
	 * Write debug summaries when explicitly enabled.
	 *
	 * @param string $message Message prefix.
	 * @param array  $context Safe context data.
	 * @return void
	 */
	protected function maybe_log_debug( $message, $context ) {
		$debug_enabled = (int) $this->settings->get( 'debug_mode' ) === 1;
		if ( ! $debug_enabled || ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			'[Size Agent] ' . sanitize_text_field( $message ) . ' | ' . wp_json_encode( $context )
		);
	}
}
