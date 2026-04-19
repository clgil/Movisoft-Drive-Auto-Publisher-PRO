<?php
/**
 * Integración exe.io.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Acorta una URL con exe.io.
 *
 * @param string $long_url URL larga.
 */
function movisoft_dap_shorten_url( string $long_url ): string {
	$options  = get_option( 'movisoft_dap_settings', array() );
	$api_key  = sanitize_text_field( $options['exeio_api_key'] ?? '' );
	$endpoint = esc_url_raw( $options['exeio_endpoint'] ?? 'https://exe.io/api' );

	if ( empty( $api_key ) ) {
		movisoft_dap_log( 'Error API exe.io: API Key vacía.' );
		return $long_url;
	}

	$url = add_query_arg(
		array(
			'api' => $api_key,
			'url' => $long_url,
		),
		$endpoint
	);

	$response = wp_remote_get(
		$url,
		array(
			'timeout' => 20,
		)
	);

	if ( is_wp_error( $response ) ) {
		movisoft_dap_log( 'Error API exe.io: ' . $response->get_error_message() );
		return $long_url;
	}

	$code = wp_remote_retrieve_response_code( $response );
	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( 200 !== $code || ! is_array( $body ) ) {
		movisoft_dap_log( 'Error API exe.io: respuesta inválida.' );
		return $long_url;
	}

	$short_url = '';
	if ( ! empty( $body['shortenedUrl'] ) ) {
		$short_url = esc_url_raw( $body['shortenedUrl'] );
	} elseif ( ! empty( $body['shortened_url'] ) ) {
		$short_url = esc_url_raw( $body['shortened_url'] );
	} elseif ( ! empty( $body['short'] ) ) {
		$short_url = esc_url_raw( $body['short'] );
	}

	if ( empty( $short_url ) ) {
		movisoft_dap_log( 'Error API exe.io: no se encontró URL corta.' );
		return $long_url;
	}

	return $short_url;
}
