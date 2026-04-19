<?php
/**
 * Integración Google Drive API v3 + OAuth2.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Obtiene redirect URI OAuth.
 */
function movisoft_dap_google_redirect_uri(): string {
	return admin_url( 'admin.php?page=movisoft-auto-publisher&oauth_callback=1' );
}

/**
 * Procesa archivo JSON de credenciales y devuelve datos normalizados.
 *
 * @param string $file_path Ruta del archivo JSON temporal.
 */
function movisoft_dap_parse_google_credentials_file( string $file_path ): array {
	$content = file_get_contents( $file_path );
	if ( ! is_string( $content ) || '' === trim( $content ) ) {
		return array(
			'success' => false,
			'error'   => 'No se pudo leer el JSON de credenciales.',
		);
	}

	$payload = json_decode( $content, true );
	if ( ! is_array( $payload ) ) {
		return array(
			'success' => false,
			'error'   => 'El archivo no contiene JSON válido.',
		);
	}

	$oauth = $payload['installed'] ?? $payload['web'] ?? null;
	if ( ! is_array( $oauth ) ) {
		return array(
			'success' => false,
			'error'   => 'El JSON no tiene estructura OAuth 2.0 Client ID válida.',
		);
	}

	$client_id     = sanitize_text_field( $oauth['client_id'] ?? '' );
	$client_secret = sanitize_text_field( $oauth['client_secret'] ?? '' );
	$auth_uri      = esc_url_raw( $oauth['auth_uri'] ?? 'https://accounts.google.com/o/oauth2/auth' );
	$token_uri     = esc_url_raw( $oauth['token_uri'] ?? 'https://oauth2.googleapis.com/token' );

	if ( empty( $client_id ) || empty( $client_secret ) || empty( $auth_uri ) || empty( $token_uri ) ) {
		return array(
			'success' => false,
			'error'   => 'Faltan claves obligatorias en el JSON (client_id/client_secret/auth_uri/token_uri).',
		);
	}

	return array(
		'success' => true,
		'data'    => array(
			'client_id'     => $client_id,
			'client_secret' => $client_secret,
			'auth_uri'      => $auth_uri,
			'token_uri'     => $token_uri,
		),
	);
}

/**
 * Guarda archivo JSON en uploads y retorna ruta final.
 *
 * @param string $tmp_path Ruta temporal.
 */
function movisoft_dap_store_google_credentials_json( string $tmp_path ): array {
	$upload_dir = wp_upload_dir();
	if ( ! empty( $upload_dir['error'] ) ) {
		return array(
			'success' => false,
			'error'   => sanitize_text_field( $upload_dir['error'] ),
		);
	}

	$destination = trailingslashit( $upload_dir['basedir'] ) . 'movisoft-drive-credentials.json';
	wp_mkdir_p( dirname( $destination ) );

	if ( ! @copy( $tmp_path, $destination ) ) {
		return array(
			'success' => false,
			'error'   => 'No se pudo guardar el archivo JSON en uploads.',
		);
	}

	return array(
		'success' => true,
		'path'    => $destination,
	);
}

/**
 * Devuelve token access válido; renueva automáticamente si expiró.
 */
function movisoft_dap_get_drive_access_token(): array {
	$options      = get_option( 'movisoft_dap_settings', array() );
	$client_id    = sanitize_text_field( $options['google_client_id'] ?? '' );
	$client_secret = sanitize_text_field( $options['google_client_secret'] ?? '' );
	$token_uri    = esc_url_raw( $options['google_token_uri'] ?? 'https://oauth2.googleapis.com/token' );
	$refresh_token = sanitize_text_field( $options['google_refresh_token'] ?? '' );
	$access_token = sanitize_text_field( $options['google_access_token'] ?? '' );
	$expires_at   = absint( $options['google_expires_at'] ?? 0 );

	if ( empty( $client_id ) || empty( $client_secret ) || empty( $token_uri ) ) {
		return array(
			'success' => false,
			'error'   => 'Credenciales Google no configuradas. Sube el JSON primero.',
		);
	}

	if ( ! empty( $access_token ) && $expires_at > ( time() + 60 ) ) {
		return array(
			'success'      => true,
			'access_token' => $access_token,
		);
	}

	if ( empty( $refresh_token ) ) {
		return array(
			'success' => false,
			'error'   => 'Refresh token no disponible. Conecta con Google.',
		);
	}

	$response = wp_remote_post(
		$token_uri,
		array(
			'timeout' => 30,
			'body'    => array(
				'client_id'     => $client_id,
				'client_secret' => $client_secret,
				'refresh_token' => $refresh_token,
				'grant_type'    => 'refresh_token',
			),
		)
	);

	if ( is_wp_error( $response ) ) {
		movisoft_dap_log( 'Error API Google Token refresh: ' . $response->get_error_message() );
		return array(
			'success' => false,
			'error'   => $response->get_error_message(),
		);
	}

	$code = wp_remote_retrieve_response_code( $response );
	$body = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( 200 !== $code || empty( $body['access_token'] ) ) {
		movisoft_dap_log( 'Error API Google Token refresh: respuesta inválida.' );
		return array(
			'success' => false,
			'error'   => 'No se pudo renovar access token.',
		);
	}

	$options['google_access_token'] = sanitize_text_field( $body['access_token'] );
	if ( ! empty( $body['refresh_token'] ) ) {
		$options['google_refresh_token'] = sanitize_text_field( $body['refresh_token'] );
	}
	$options['google_expires_in'] = absint( $body['expires_in'] ?? HOUR_IN_SECONDS );
	$options['google_expires_at'] = time() + $options['google_expires_in'];
	update_option( 'movisoft_dap_settings', $options );

	return array(
		'success'      => true,
		'access_token' => $options['google_access_token'],
	);
}

/**
 * Intercambia authorization code por tokens.
 *
 * @param string $authorization_code Code OAuth.
 */
function movisoft_dap_exchange_google_auth_code( string $authorization_code ): array {
	$options       = get_option( 'movisoft_dap_settings', array() );
	$client_id     = sanitize_text_field( $options['google_client_id'] ?? '' );
	$client_secret = sanitize_text_field( $options['google_client_secret'] ?? '' );
	$token_uri     = esc_url_raw( $options['google_token_uri'] ?? 'https://oauth2.googleapis.com/token' );

	if ( empty( $client_id ) || empty( $client_secret ) || empty( $token_uri ) ) {
		return array(
			'success' => false,
			'error'   => 'Credenciales OAuth incompletas.',
		);
	}

	$response = wp_remote_post(
		$token_uri,
		array(
			'timeout' => 30,
			'body'    => array(
				'code'          => sanitize_text_field( $authorization_code ),
				'client_id'     => $client_id,
				'client_secret' => $client_secret,
				'redirect_uri'  => movisoft_dap_google_redirect_uri(),
				'grant_type'    => 'authorization_code',
			),
		)
	);

	if ( is_wp_error( $response ) ) {
		movisoft_dap_log( 'Error OAuth code exchange: ' . $response->get_error_message() );
		return array(
			'success' => false,
			'error'   => $response->get_error_message(),
		);
	}

	$code = wp_remote_retrieve_response_code( $response );
	$body = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( 200 !== $code || empty( $body['access_token'] ) ) {
		movisoft_dap_log( 'Error OAuth code exchange: respuesta inválida.' );
		return array(
			'success' => false,
			'error'   => 'No se pudo intercambiar authorization code.',
		);
	}

	$options['google_access_token'] = sanitize_text_field( $body['access_token'] );
	$options['google_refresh_token'] = sanitize_text_field( $body['refresh_token'] ?? ( $options['google_refresh_token'] ?? '' ) );
	$options['google_expires_in']   = absint( $body['expires_in'] ?? HOUR_IN_SECONDS );
	$options['google_expires_at']   = time() + $options['google_expires_in'];

	update_option( 'movisoft_dap_settings', $options );

	return array(
		'success' => true,
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
	$url       = add_query_arg(
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
