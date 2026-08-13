<?php
/**
 * HTTP client for manage.astrallabs.uk.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Astralab_Client {

	/**
	 * Ask the hub to issue a licence for a paid order.
	 *
	 * Returns an array on success, or WP_Error. The caller must treat a
	 * successful response as the ONLY chance to capture the plaintext key.
	 *
	 * @param array $payload productSlug, orderRef, customerEmail, customerName.
	 * @return array|WP_Error
	 */
	public static function issue_licence( $payload ) {
		$base   = Astralab_Settings::hub_url();
		$secret = Astralab_Settings::api_secret();

		if ( empty( $base ) || empty( $secret ) ) {
			return new WP_Error(
				'astralab_not_configured',
				__( 'The licence hub URL or API secret is not set (WooCommerce → Settings → Astra Lab).', 'astralab-licence' )
			);
		}

		// The hub verifies the HMAC against the exact bytes it receives, so the
		// body must be encoded once and both signed and sent in that same form.
		// Re-encoding would reorder keys and invalidate the signature.
		$body      = wp_json_encode( $payload );
		$signature = hash_hmac( 'sha256', $body, $secret );

		$response = wp_remote_post(
			trailingslashit( $base ) . 'api/v1/licences',
			array(
				'timeout' => 20,
				'headers' => array(
					'Content-Type'         => 'application/json',
					'X-Astralab-Signature' => $signature,
				),
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 201 !== $code && 200 !== $code ) {
			$detail = is_array( $data ) && isset( $data['error'] ) ? $data['error'] : 'http_' . $code;
			return new WP_Error( 'astralab_issue_failed', $detail, array( 'status' => $code ) );
		}

		if ( ! is_array( $data ) ) {
			return new WP_Error( 'astralab_bad_response', __( 'The hub returned an unreadable response.', 'astralab-licence' ) );
		}

		return $data;
	}
}
