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
 * Parsea JSON OAuth.
 *
 * @param string $file_path Ruta temporal.
 */
function movisoft_dap_parse_google_credentials_file( string $file_path ): array {
	$content = file_get_contents( $file_path );
	if ( ! is_string( $content ) || '' === trim( $content ) ) {
		return array( 'success' => false, 'error' => 'No se pudo leer el JSON de credenciales.' );
	}

	$payload = json_decode( $content, true );
	if ( ! is_array( $payload ) ) {
		return array( 'success' => false, 'error' => 'El archivo no contiene JSON válido.' );
	}

	$oauth = $payload['installed'] ?? $payload['web'] ?? null;
	if ( ! is_array( $oauth ) ) {
		return array( 'success' => false, 'error' => 'El JSON no tiene estructura OAuth 2.0 Client ID válida.' );
	}

	$data = array(
		'client_id'     => sanitize_text_field( $oauth['client_id'] ?? '' ),
		'client_secret' => sanitize_text_field( $oauth['client_secret'] ?? '' ),
		'auth_uri'      => esc_url_raw( $oauth['auth_uri'] ?? 'https://accounts.google.com/o/oauth2/auth' ),
		'token_uri'     => esc_url_raw( $oauth['token_uri'] ?? 'https://oauth2.googleapis.com/token' ),
	);

	if ( empty( $data['client_id'] ) || empty( $data['client_secret'] ) || empty( $data['auth_uri'] ) || empty( $data['token_uri'] ) ) {
		return array( 'success' => false, 'error' => 'Faltan claves obligatorias en el JSON.' );
	}

	return array( 'success' => true, 'data' => $data );
}

/**
 * Valida MIME real del JSON.
 *
 * @param string $tmp_path Ruta temporal.
 * @param string $file_name Nombre original.
 */
function movisoft_dap_validate_json_upload( string $tmp_path, string $file_name ): array {
	$ext = strtolower( pathinfo( $file_name, PATHINFO_EXTENSION ) );
	if ( 'json' !== $ext ) {
		return array( 'success' => false, 'error' => 'La extensión del archivo debe ser .json' );
	}

	$wp_ft = wp_check_filetype_and_ext( $tmp_path, $file_name );
	$mime  = sanitize_text_field( $wp_ft['type'] ?? '' );

	$allowed = array(
		'application/json',
		'text/plain',
		'text/json',
	);

	if ( ! empty( $mime ) && ! in_array( $mime, $allowed, true ) ) {
		return array( 'success' => false, 'error' => 'MIME no permitido para JSON.' );
	}

	if ( function_exists( 'finfo_open' ) ) {
		$finfo = finfo_open( FILEINFO_MIME_TYPE );
		if ( false !== $finfo ) {
			$detected = finfo_file( $finfo, $tmp_path );
			finfo_close( $finfo );
			if ( is_string( $detected ) && ! in_array( $detected, $allowed, true ) ) {
				return array( 'success' => false, 'error' => 'El contenido no parece JSON válido.' );
			}
		}
	}

	return array( 'success' => true );
}

/**
 * Escribe regla de protección al JSON.
 */
function movisoft_dap_protect_credentials_json_file( string $json_path ): void {
	$upload_dir     = wp_upload_dir();
	$htaccess_path  = trailingslashit( $upload_dir['basedir'] ) . '.htaccess';
	$rule_start     = '# BEGIN Movisoft Credentials Protect';
	$rule_end       = '# END Movisoft Credentials Protect';
	$rule_block     = $rule_start . "\n<Files \"movisoft-drive-credentials.json\">\n\tRequire all denied\n\tDeny from all\n</Files>\n" . $rule_end;
	$current_rules  = file_exists( $htaccess_path ) ? file_get_contents( $htaccess_path ) : '';
	$current_rules  = is_string( $current_rules ) ? $current_rules : '';

	if ( false === strpos( $current_rules, $rule_start ) ) {
		file_put_contents( $htaccess_path, trim( $current_rules ) . "\n\n" . $rule_block . "\n", LOCK_EX );
	}

	if ( file_exists( $json_path ) ) {
		@chmod( $json_path, 0600 );
	}
}

/**
 * Guarda JSON en uploads.
 *
 * @param string $tmp_path Ruta temporal.
 */
function movisoft_dap_store_google_credentials_json( string $tmp_path ): array {
	$upload_dir = wp_upload_dir();
	if ( ! empty( $upload_dir['error'] ) ) {
		return array( 'success' => false, 'error' => sanitize_text_field( $upload_dir['error'] ) );
	}

	$destination = trailingslashit( $upload_dir['basedir'] ) . 'movisoft-drive-credentials.json';
	wp_mkdir_p( dirname( $destination ) );

	if ( ! @copy( $tmp_path, $destination ) ) {
		return array( 'success' => false, 'error' => 'No se pudo guardar el archivo JSON en uploads.' );
	}

	movisoft_dap_protect_credentials_json_file( $destination );

	return array( 'success' => true, 'path' => $destination );
}

/**
 * Intercambio code por tokens.
 *
 * @param string $authorization_code Code OAuth.
 */
function movisoft_dap_exchange_google_auth_code( string $authorization_code ): array {
	$options       = get_option( 'movisoft_dap_settings', array() );
	$client_id     = sanitize_text_field( $options['google_client_id'] ?? '' );
	$client_secret = sanitize_text_field( $options['google_client_secret'] ?? '' );
	$token_uri     = esc_url_raw( $options['google_token_uri'] ?? 'https://oauth2.googleapis.com/token' );

	if ( empty( $client_id ) || empty( $client_secret ) || empty( $token_uri ) ) {
		return array( 'success' => false, 'error' => 'Credenciales OAuth incompletas.' );
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
		return array( 'success' => false, 'error' => $response->get_error_message() );
	}

	$body = json_decode( wp_remote_retrieve_body( $response ), true );
	$code = wp_remote_retrieve_response_code( $response );
	if ( 200 !== $code || empty( $body['access_token'] ) ) {
		return array( 'success' => false, 'error' => 'No se pudo intercambiar authorization_code.' );
	}

	$options['google_access_token']  = sanitize_text_field( $body['access_token'] );
	$options['google_refresh_token'] = sanitize_text_field( $body['refresh_token'] ?? ( $options['google_refresh_token'] ?? '' ) );
	$options['google_expires_in']    = absint( $body['expires_in'] ?? HOUR_IN_SECONDS );
	$options['google_expires_at']    = time() + $options['google_expires_in'];
	update_option( 'movisoft_dap_settings', $options );

	return array( 'success' => true );
}

/**
 * Obtiene access token válido y renueva si expira.
 */
function movisoft_dap_get_drive_access_token(): array {
	$options        = get_option( 'movisoft_dap_settings', array() );
	$access_token   = sanitize_text_field( $options['google_access_token'] ?? '' );
	$refresh_token  = sanitize_text_field( $options['google_refresh_token'] ?? '' );
	$expires_at     = absint( $options['google_expires_at'] ?? 0 );
	$client_id      = sanitize_text_field( $options['google_client_id'] ?? '' );
	$client_secret  = sanitize_text_field( $options['google_client_secret'] ?? '' );
	$token_uri      = esc_url_raw( $options['google_token_uri'] ?? 'https://oauth2.googleapis.com/token' );

	if ( ! empty( $access_token ) && $expires_at > ( time() + 60 ) ) {
		return array( 'success' => true, 'access_token' => $access_token );
	}

	if ( empty( $refresh_token ) || empty( $client_id ) || empty( $client_secret ) ) {
		return array( 'success' => false, 'error' => 'Conecta Google para obtener token.' );
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
		return array( 'success' => false, 'error' => $response->get_error_message() );
	}

	$body = json_decode( wp_remote_retrieve_body( $response ), true );
	$code = wp_remote_retrieve_response_code( $response );
	if ( 200 !== $code || empty( $body['access_token'] ) ) {
		return array( 'success' => false, 'error' => 'No se pudo renovar token.' );
	}

	$options['google_access_token'] = sanitize_text_field( $body['access_token'] );
	if ( ! empty( $body['refresh_token'] ) ) {
		$options['google_refresh_token'] = sanitize_text_field( $body['refresh_token'] );
	}
	$options['google_expires_in'] = absint( $body['expires_in'] ?? HOUR_IN_SECONDS );
	$options['google_expires_at'] = time() + $options['google_expires_in'];
	update_option( 'movisoft_dap_settings', $options );

	return array( 'success' => true, 'access_token' => $options['google_access_token'] );
}

/**
 * GET autenticado a Google Drive.
 *
 * @param string $url URL endpoint.
 */
function movisoft_dap_drive_authenticated_get( string $url ): array {
	$token = movisoft_dap_get_drive_access_token();
	if ( empty( $token['success'] ) ) {
		return array( 'success' => false, 'error' => sanitize_text_field( $token['error'] ?? 'Token inválido.' ) );
	}

	$response = wp_remote_get(
		$url,
		array(
			'timeout' => 25,
			'headers' => array(
				'Authorization' => 'Bearer ' . $token['access_token'],
			),
		)
	);

	if ( is_wp_error( $response ) ) {
		return array( 'success' => false, 'error' => $response->get_error_message() );
	}

	$code = wp_remote_retrieve_response_code( $response );
	$body = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( 200 !== $code || ! is_array( $body ) ) {
		return array( 'success' => false, 'error' => 'Respuesta inválida de Google Drive.' );
	}

	return array( 'success' => true, 'body' => $body );
}

/**
 * Lista carpetas Google Drive del usuario.
 */
function movisoft_dap_list_drive_folders(): array {
	$url = add_query_arg(
		array(
			'q'                         => "mimeType='application/vnd.google-apps.folder' and trashed = false",
			'fields'                    => 'files(id,name)',
			'pageSize'                  => 1000,
			'supportsAllDrives'         => 'true',
			'includeItemsFromAllDrives' => 'true',
		),
		'https://www.googleapis.com/drive/v3/files'
	);

	$result = movisoft_dap_drive_authenticated_get( $url );
	if ( empty( $result['success'] ) ) {
		return $result;
	}

	$folders = array();
	foreach ( $result['body']['files'] ?? array() as $folder ) {
		$folders[] = array(
			'id'   => sanitize_text_field( $folder['id'] ?? '' ),
			'name' => sanitize_text_field( $folder['name'] ?? '' ),
		);
	}

	return array( 'success' => true, 'folders' => $folders );
}

/**
 * Lista archivos de una carpeta.
 *
 * @param string $folder_id Folder ID.
 */
function movisoft_dap_list_drive_files( string $folder_id ): array {
	$folder_id = sanitize_text_field( $folder_id );
	$query     = sprintf( "'%s' in parents and trashed = false", $folder_id );
	$url       = add_query_arg(
		array(
			'q'                         => $query,
			'fields'                    => 'files(id,name,mimeType,size)',
			'pageSize'                  => 1000,
			'supportsAllDrives'         => 'true',
			'includeItemsFromAllDrives' => 'true',
		),
		'https://www.googleapis.com/drive/v3/files'
	);

	$result = movisoft_dap_drive_authenticated_get( $url );
	if ( empty( $result['success'] ) ) {
		movisoft_dap_log( 'Error API Google List: ' . sanitize_text_field( $result['error'] ?? 'desconocido' ) );
		return $result;
	}

	$files = array();
	foreach ( $result['body']['files'] ?? array() as $file ) {
		$file_id = sanitize_text_field( $file['id'] ?? '' );
		$files[] = array(
			'id'        => $file_id,
			'name'      => sanitize_text_field( $file['name'] ?? '' ),
			'mimeType'  => sanitize_text_field( $file['mimeType'] ?? '' ),
			'size'      => absint( $file['size'] ?? 0 ),
			'published' => movisoft_dap_is_duplicate( $file_id ),
		);
	}

	return array( 'success' => true, 'files' => $files );
}
