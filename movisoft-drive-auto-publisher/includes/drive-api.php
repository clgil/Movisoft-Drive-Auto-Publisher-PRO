<?php
/**
 * Integración Google Drive API v3.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Obtiene access token con refresh token.
 */
function movisoft_dap_get_drive_access_token(): array {
	$options       = get_option( 'movisoft_dap_settings', array() );
	$client_id     = sanitize_text_field( $options['google_client_id'] ?? '' );
	$client_secret = sanitize_text_field( $options['google_client_secret'] ?? '' );
	$refresh_token = sanitize_text_field( $options['google_refresh_token'] ?? '' );

	if ( empty( $client_id ) || empty( $client_secret ) || empty( $refresh_token ) ) {
		return array(
			'success' => false,
			'error'   => 'Credenciales de Google incompletas.',
		);
	}

	$response = wp_remote_post(
		'https://oauth2.googleapis.com/token',
		array(
			'timeout' => 25,
			'body'    => array(
				'client_id'     => $client_id,
				'client_secret' => $client_secret,
				'refresh_token' => $refresh_token,
				'grant_type'    => 'refresh_token',
			),
		)
	);

	if ( is_wp_error( $response ) ) {
		movisoft_dap_log( 'Error API Google Token: ' . $response->get_error_message() );
		return array(
			'success' => false,
			'error'   => $response->get_error_message(),
		);
	}

	$code = wp_remote_retrieve_response_code( $response );
	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( 200 !== $code || empty( $body['access_token'] ) ) {
		movisoft_dap_log( 'Error API Google Token: respuesta inválida.' );
		return array(
			'success' => false,
			'error'   => 'No se obtuvo access token.',
		);
	}

	return array(
		'success'      => true,
		'access_token' => sanitize_text_field( $body['access_token'] ),
	);
}

/**
 * Lista archivos de una carpeta.
 *
 * @param string $folder_id Folder ID.
 */
function movisoft_dap_list_drive_files( string $folder_id ): array {
	$token_result = movisoft_dap_get_drive_access_token();
	if ( empty( $token_result['success'] ) ) {
		return array(
			'success' => false,
			'error'   => $token_result['error'] ?? 'Error de autenticación Google.',
		);
	}

	$folder_id = sanitize_text_field( $folder_id );
	$query     = sprintf( "'%s' in parents and trashed = false", $folder_id );
	$url   = add_query_arg(
		array(
			'q'                         => $query,
			'fields'                    => 'files(id,name,mimeType,webViewLink)',
			'supportsAllDrives'         => 'true',
			'includeItemsFromAllDrives' => 'true',
			'pageSize'                  => 1000,
		),
		'https://www.googleapis.com/drive/v3/files'
	);

	$response = wp_remote_get(
		$url,
		array(
			'timeout' => 25,
			'headers' => array(
				'Authorization' => 'Bearer ' . $token_result['access_token'],
			),
		)
	);

	if ( is_wp_error( $response ) ) {
		movisoft_dap_log( 'Error API Google List: ' . $response->get_error_message() );
		return array(
			'success' => false,
			'error'   => $response->get_error_message(),
		);
	}

	$code = wp_remote_retrieve_response_code( $response );
	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( 200 !== $code || ! is_array( $body ) ) {
		movisoft_dap_log( 'Error API Google List: respuesta inválida.' );
		return array(
			'success' => false,
			'error'   => 'No se pudieron listar archivos.',
		);
	}

	$files = array();
	foreach ( $body['files'] ?? array() as $file ) {
		$files[] = array(
			'id'       => sanitize_text_field( $file['id'] ?? '' ),
			'name'     => sanitize_text_field( $file['name'] ?? '' ),
			'mimeType' => sanitize_text_field( $file['mimeType'] ?? '' ),
		);
	}

	return array(
		'success' => true,
		'files'   => $files,
	);
}
