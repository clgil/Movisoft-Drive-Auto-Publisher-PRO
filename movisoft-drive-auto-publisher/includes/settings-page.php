<?php
/**
 * Render y lógica de páginas admin.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registra settings.
 */
function movisoft_dap_register_settings(): void {
	register_setting(
		'movisoft_dap_settings_group',
		'movisoft_dap_settings',
		array(
			'sanitize_callback' => 'movisoft_dap_sanitize_settings',
		)
	);
}
add_action( 'admin_init', 'movisoft_dap_register_settings' );

/**
 * Sanitiza opciones del plugin.
 *
 * @param array $input Input.
 */
function movisoft_dap_sanitize_settings( array $input ): array {
	$current = get_option( 'movisoft_dap_settings', array() );
	$output  = is_array( $current ) ? $current : array();

	$output['google_folder_id'] = sanitize_text_field( $input['google_folder_id'] ?? '' );
	$output['folder_brand']     = sanitize_text_field( $input['folder_brand'] ?? '' );

	if ( ! empty( $_FILES['movisoft_google_credentials_json']['tmp_name'] ) ) {
		$file_name = sanitize_file_name( wp_unslash( $_FILES['movisoft_google_credentials_json']['name'] ?? '' ) );
		$tmp_path  = sanitize_text_field( wp_unslash( $_FILES['movisoft_google_credentials_json']['tmp_name'] ?? '' ) );

		if ( 'json' !== strtolower( pathinfo( $file_name, PATHINFO_EXTENSION ) ) ) {
			add_settings_error( 'movisoft_dap_settings', 'movisoft_json_invalid_ext', 'El archivo de credenciales debe ser JSON.' );
		} else {
			$parsed = movisoft_dap_parse_google_credentials_file( $tmp_path );
			if ( empty( $parsed['success'] ) ) {
				add_settings_error( 'movisoft_dap_settings', 'movisoft_json_invalid', sanitize_text_field( $parsed['error'] ?? 'JSON inválido.' ) );
			} else {
				$saved = movisoft_dap_store_google_credentials_json( $tmp_path );
				if ( empty( $saved['success'] ) ) {
					add_settings_error( 'movisoft_dap_settings', 'movisoft_json_save_error', sanitize_text_field( $saved['error'] ?? 'No se pudo guardar JSON.' ) );
				} else {
					$output['google_credentials_file'] = sanitize_text_field( $saved['path'] );
					$output['google_client_id']        = sanitize_text_field( $parsed['data']['client_id'] );
					$output['google_client_secret']    = sanitize_text_field( $parsed['data']['client_secret'] );
					$output['google_auth_uri']         = esc_url_raw( $parsed['data']['auth_uri'] );
					$output['google_token_uri']        = esc_url_raw( $parsed['data']['token_uri'] );
					add_settings_error( 'movisoft_dap_settings', 'movisoft_json_ok', 'JSON de credenciales cargado correctamente.', 'updated' );
				}
			}
		}
	}

	$output['exeio_api_key']  = sanitize_text_field( $input['exeio_api_key'] ?? '' );
	$output['exeio_endpoint'] = esc_url_raw( $input['exeio_endpoint'] ?? 'https://exe.io/api' );

	$output['openrouter_enabled'] = ! empty( $input['openrouter_enabled'] ) ? 1 : 0;
	$output['openrouter_api_key'] = sanitize_text_field( $input['openrouter_api_key'] ?? '' );
	$output['openrouter_prompt']  = wp_kses_post( $input['openrouter_prompt'] ?? '' );

	$output['default_category']  = absint( $input['default_category'] ?? 0 );
	$output['default_tags']      = sanitize_text_field( $input['default_tags'] ?? '' );
	$output['featured_image_id'] = absint( $input['featured_image_id'] ?? 0 );

	$output['publish_mode'] = in_array( $input['publish_mode'] ?? 'publish', array( 'publish', 'future' ), true ) ? $input['publish_mode'] : 'publish';
	$output['cron_enabled'] = ! empty( $input['cron_enabled'] ) ? 1 : 0;

	return $output;
}

/**
 * Dashboard.
 */
function movisoft_dap_render_dashboard_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'No tienes permisos suficientes.', 'movisoft-drive-auto-publisher' ) );
	}

	$options = get_option( 'movisoft_dap_settings', array() );
	$state   = ! empty( $options['google_refresh_token'] ) ? 'Conectado' : 'No conectado';
	?>
	<div class="wrap movisoft-dap-wrap">
		<h1><?php echo esc_html__( 'Movisoft Drive Auto Publisher PRO', 'movisoft-drive-auto-publisher' ); ?></h1>
		<div class="movisoft-grid">
			<div class="movisoft-card">
				<h2><?php echo esc_html__( 'Estado', 'movisoft-drive-auto-publisher' ); ?></h2>
				<p><strong><?php echo esc_html__( 'Google OAuth:', 'movisoft-drive-auto-publisher' ); ?></strong> <?php echo esc_html( $state ); ?></p>
				<p><strong><?php echo esc_html__( 'Carpeta Drive ID:', 'movisoft-drive-auto-publisher' ); ?></strong> <?php echo esc_html( $options['google_folder_id'] ?? '-' ); ?></p>
				<p><strong><?php echo esc_html__( 'IA:', 'movisoft-drive-auto-publisher' ); ?></strong> <?php echo ! empty( $options['openrouter_enabled'] ) ? esc_html__( 'Activa', 'movisoft-drive-auto-publisher' ) : esc_html__( 'Desactivada', 'movisoft-drive-auto-publisher' ); ?></p>
				<p><strong><?php echo esc_html__( 'Cron:', 'movisoft-drive-auto-publisher' ); ?></strong> <?php echo ! empty( $options['cron_enabled'] ) ? esc_html__( 'Activo (6h)', 'movisoft-drive-auto-publisher' ) : esc_html__( 'Inactivo', 'movisoft-drive-auto-publisher' ); ?></p>
			</div>
		</div>
	</div>
	<?php
}

/**
 * Configuración APIs.
 */
function movisoft_dap_render_settings_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'No tienes permisos suficientes.', 'movisoft-drive-auto-publisher' ) );
	}

	$options        = get_option( 'movisoft_dap_settings', array() );
	$connect_url    = wp_nonce_url( admin_url( 'admin-post.php?action=movisoft_dap_google_oauth_start' ), 'movisoft_dap_google_oauth_start' );
	$is_connected   = ! empty( $options['google_refresh_token'] );
	$credentials_ok = ! empty( $options['google_client_id'] ) && ! empty( $options['google_client_secret'] );
	?>
	<div class="wrap movisoft-dap-wrap">
		<h1><?php echo esc_html__( 'Configuración API', 'movisoft-drive-auto-publisher' ); ?></h1>
		<?php settings_errors( 'movisoft_dap_settings' ); ?>
		<form method="post" action="options.php" enctype="multipart/form-data">
			<?php settings_fields( 'movisoft_dap_settings_group' ); ?>
			<table class="form-table" role="presentation">
				<tr><th colspan="2"><h2>Google Drive OAuth2 (JSON obligatorio)</h2></th></tr>
				<tr>
					<th><label for="movisoft_google_credentials_json">Credenciales JSON</label></th>
					<td>
						<input type="file" id="movisoft_google_credentials_json" name="movisoft_google_credentials_json" accept="application/json,.json" />
						<p class="description">Sube el JSON OAuth 2.0 Client ID descargado desde Google Cloud Console.</p>
						<?php if ( ! empty( $options['google_credentials_file'] ) ) : ?>
							<p><strong>Archivo guardado:</strong> <?php echo esc_html( $options['google_credentials_file'] ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th><label for="google_folder_id">Folder ID</label></th>
					<td><input type="text" id="google_folder_id" name="movisoft_dap_settings[google_folder_id]" value="<?php echo esc_attr( $options['google_folder_id'] ?? '' ); ?>" class="regular-text" /></td>
				</tr>
				<tr>
					<th><label for="folder_brand">Marca de carpeta</label></th>
					<td><input type="text" id="folder_brand" name="movisoft_dap_settings[folder_brand]" value="<?php echo esc_attr( $options['folder_brand'] ?? '' ); ?>" class="regular-text" /></td>
				</tr>
				<tr>
					<th>OAuth</th>
					<td>
						<?php if ( $credentials_ok ) : ?>
							<a href="<?php echo esc_url( $connect_url ); ?>" class="button button-secondary">Conectar con Google</a>
							<p><strong>Estado:</strong> <?php echo $is_connected ? esc_html__( 'Conectado', 'movisoft-drive-auto-publisher' ) : esc_html__( 'No conectado', 'movisoft-drive-auto-publisher' ); ?></p>
						<?php else : ?>
							<p>Carga el JSON para habilitar la conexión OAuth.</p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th>Test conexión</th>
					<td>
						<?php submit_button( 'Test conexión Google Drive', 'secondary', 'movisoft_dap_test_drive', false ); ?>
						<?php if ( isset( $_GET['movisoft_drive_test'] ) ) : ?>
							<p><strong><?php echo 'ok' === sanitize_text_field( wp_unslash( $_GET['movisoft_drive_test'] ) ) ? esc_html__( 'Conexión exitosa.', 'movisoft-drive-auto-publisher' ) : esc_html__( 'Conexión fallida.', 'movisoft-drive-auto-publisher' ); ?></strong></p>
						<?php endif; ?>
					</td>
				</tr>

				<tr><th colspan="2"><h2>exe.io</h2></th></tr>
				<tr>
					<th><label for="exeio_api_key">API Key</label></th>
					<td><input type="password" id="exeio_api_key" name="movisoft_dap_settings[exeio_api_key]" value="<?php echo esc_attr( $options['exeio_api_key'] ?? '' ); ?>" class="regular-text" /></td>
				</tr>
				<tr>
					<th><label for="exeio_endpoint">Endpoint</label></th>
					<td><input type="url" id="exeio_endpoint" name="movisoft_dap_settings[exeio_endpoint]" value="<?php echo esc_attr( $options['exeio_endpoint'] ?? 'https://exe.io/api' ); ?>" class="regular-text" /></td>
				</tr>

				<tr><th colspan="2"><h2>OpenRouter</h2></th></tr>
				<tr>
					<th><label for="openrouter_enabled">Activar IA</label></th>
					<td><label><input type="checkbox" id="openrouter_enabled" name="movisoft_dap_settings[openrouter_enabled]" value="1" <?php checked( ! empty( $options['openrouter_enabled'] ) ); ?> /> Usar OpenRouter</label></td>
				</tr>
				<tr>
					<th><label for="openrouter_api_key">API Key</label></th>
					<td><input type="password" id="openrouter_api_key" name="movisoft_dap_settings[openrouter_api_key]" value="<?php echo esc_attr( $options['openrouter_api_key'] ?? '' ); ?>" class="regular-text" /></td>
				</tr>
				<tr>
					<th><label for="openrouter_prompt">Prompt base</label></th>
					<td><textarea id="openrouter_prompt" name="movisoft_dap_settings[openrouter_prompt]" class="large-text" rows="6"><?php echo esc_textarea( $options['openrouter_prompt'] ?? '' ); ?></textarea></td>
				</tr>

				<tr><th colspan="2"><h2>Publicación</h2></th></tr>
				<tr>
					<th><label for="publish_mode">Modo</label></th>
					<td>
						<select id="publish_mode" name="movisoft_dap_settings[publish_mode]">
							<option value="publish" <?php selected( $options['publish_mode'] ?? 'publish', 'publish' ); ?>>Publicar ahora</option>
							<option value="future" <?php selected( $options['publish_mode'] ?? 'publish', 'future' ); ?>>Programar +1 hora</option>
						</select>
					</td>
				</tr>
				<tr>
					<th><label for="default_category">Categoría por defecto</label></th>
					<td><?php wp_dropdown_categories( array( 'name' => 'movisoft_dap_settings[default_category]', 'id' => 'default_category', 'selected' => absint( $options['default_category'] ?? 0 ), 'show_option_none' => '— Ninguna —', 'hide_empty' => false ) ); ?></td>
				</tr>
				<tr>
					<th><label for="default_tags">Etiquetas por defecto</label></th>
					<td><input type="text" id="default_tags" name="movisoft_dap_settings[default_tags]" value="<?php echo esc_attr( $options['default_tags'] ?? '' ); ?>" class="regular-text" /><p class="description">Separar por coma.</p></td>
				</tr>
				<tr>
					<th><label for="featured_image_id">Attachment ID imagen destacada</label></th>
					<td><input type="number" id="featured_image_id" name="movisoft_dap_settings[featured_image_id]" value="<?php echo esc_attr( $options['featured_image_id'] ?? '' ); ?>" class="small-text" min="0" /></td>
				</tr>
				<tr>
					<th><label for="cron_enabled">Cron automático</label></th>
					<td><label><input type="checkbox" id="cron_enabled" name="movisoft_dap_settings[cron_enabled]" value="1" <?php checked( ! empty( $options['cron_enabled'] ) ); ?> /> Ejecutar importación cada 6 horas</label></td>
				</tr>
			</table>
			<?php submit_button( 'Guardar configuración' ); ?>
		</form>
	</div>
	<?php
}

/**
 * Inicia flujo OAuth redirigiendo a Google.
 */
function movisoft_dap_handle_google_oauth_start(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'No autorizado.', 'movisoft-drive-auto-publisher' ) );
	}

	check_admin_referer( 'movisoft_dap_google_oauth_start' );

	$options  = get_option( 'movisoft_dap_settings', array() );
	$auth_uri = esc_url_raw( $options['google_auth_uri'] ?? 'https://accounts.google.com/o/oauth2/auth' );
	$client_id = sanitize_text_field( $options['google_client_id'] ?? '' );

	if ( empty( $client_id ) ) {
		wp_safe_redirect( add_query_arg( array( 'page' => 'movisoft-auto-publisher-settings', 'oauth' => 'missing_json' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	$state = wp_generate_password( 24, false, false );
	set_transient( 'movisoft_dap_oauth_state_' . get_current_user_id(), $state, 10 * MINUTE_IN_SECONDS );

	$auth_url = add_query_arg(
		array(
			'client_id'              => $client_id,
			'redirect_uri'           => movisoft_dap_google_redirect_uri(),
			'response_type'          => 'code',
			'scope'                  => 'https://www.googleapis.com/auth/drive.readonly',
			'access_type'            => 'offline',
			'prompt'                 => 'consent',
			'include_granted_scopes' => 'true',
			'state'                  => $state,
		),
		$auth_uri
	);

	wp_safe_redirect( $auth_url );
	exit;
}
add_action( 'admin_post_movisoft_dap_google_oauth_start', 'movisoft_dap_handle_google_oauth_start' );

/**
 * Callback OAuth.
 */
function movisoft_dap_handle_google_oauth_callback(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$page = sanitize_text_field( wp_unslash( $_GET['page'] ?? '' ) );
	$cb   = absint( $_GET['oauth_callback'] ?? 0 );
	if ( 'movisoft-auto-publisher' !== $page || 1 !== $cb ) {
		return;
	}

	if ( ! empty( $_GET['error'] ) ) {
		movisoft_dap_log( 'OAuth cancelado por usuario: ' . sanitize_text_field( wp_unslash( $_GET['error'] ) ) );
		wp_safe_redirect( add_query_arg( array( 'page' => 'movisoft-auto-publisher-settings', 'oauth' => 'error' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	$code  = sanitize_text_field( wp_unslash( $_GET['code'] ?? '' ) );
	$state = sanitize_text_field( wp_unslash( $_GET['state'] ?? '' ) );
	$saved = get_transient( 'movisoft_dap_oauth_state_' . get_current_user_id() );

	if ( empty( $code ) || empty( $state ) || empty( $saved ) || ! hash_equals( $saved, $state ) ) {
		movisoft_dap_log( 'OAuth callback inválido: state/code.' );
		wp_safe_redirect( add_query_arg( array( 'page' => 'movisoft-auto-publisher-settings', 'oauth' => 'invalid_state' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	delete_transient( 'movisoft_dap_oauth_state_' . get_current_user_id() );
	$result = movisoft_dap_exchange_google_auth_code( $code );

	if ( empty( $result['success'] ) ) {
		movisoft_dap_log( 'OAuth exchange error: ' . sanitize_text_field( $result['error'] ?? 'desconocido' ) );
		wp_safe_redirect( add_query_arg( array( 'page' => 'movisoft-auto-publisher-settings', 'oauth' => 'token_error' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	wp_safe_redirect( add_query_arg( array( 'page' => 'movisoft-auto-publisher-settings', 'oauth' => 'connected' ), admin_url( 'admin.php' ) ) );
	exit;
}
add_action( 'admin_init', 'movisoft_dap_handle_google_oauth_callback' );

/**
 * Acción de test de conexión Google.
 */
function movisoft_dap_handle_test_drive(): void {
	if ( ! isset( $_POST['movisoft_dap_test_drive'] ) ) {
		return;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	check_admin_referer( 'movisoft_dap_settings_group-options' );

	$options   = get_option( 'movisoft_dap_settings', array() );
	$folder_id = sanitize_text_field( $options['google_folder_id'] ?? '' );
	$result    = movisoft_dap_list_drive_files( $folder_id );
	$status    = ! empty( $result['success'] ) ? 'ok' : 'error';

	wp_safe_redirect(
		add_query_arg(
			array(
				'page'               => 'movisoft-auto-publisher-settings',
				'movisoft_drive_test' => $status,
			),
			admin_url( 'admin.php' )
		)
	);
	exit;
}
add_action( 'admin_init', 'movisoft_dap_handle_test_drive' );

/**
 * AJAX inicia importación.
 */
function movisoft_dap_ajax_start_import(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Permiso denegado.' ) );
	}

	check_ajax_referer( 'movisoft_dap_import_nonce', 'nonce' );

	$options   = get_option( 'movisoft_dap_settings', array() );
	$folder_id = sanitize_text_field( $options['google_folder_id'] ?? '' );
	if ( empty( $folder_id ) ) {
		wp_send_json_error( array( 'message' => 'Folder ID no configurado.' ) );
	}

	$list = movisoft_dap_list_drive_files( $folder_id );
	if ( empty( $list['success'] ) ) {
		wp_send_json_error( array( 'message' => $list['error'] ?? 'Error listando archivos.' ) );
	}

	set_transient( 'movisoft_dap_drive_files_' . get_current_user_id(), $list['files'], 20 * MINUTE_IN_SECONDS );

	wp_send_json_success(
		array(
			'total' => count( $list['files'] ),
		)
	);
}
add_action( 'wp_ajax_movisoft_dap_start_import', 'movisoft_dap_ajax_start_import' );

/**
 * AJAX procesa lote.
 */
function movisoft_dap_ajax_process_batch(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Permiso denegado.' ) );
	}

	check_ajax_referer( 'movisoft_dap_import_nonce', 'nonce' );

	$offset = absint( $_POST['offset'] ?? 0 );
	$limit  = 10;
	$files  = get_transient( 'movisoft_dap_drive_files_' . get_current_user_id() );
	if ( ! is_array( $files ) ) {
		wp_send_json_error( array( 'message' => 'Sesión de importación expirada.' ) );
	}

	$options    = get_option( 'movisoft_dap_settings', array() );
	$folder_tag = sanitize_text_field( $options['folder_brand'] ?? '' );
	if ( empty( $folder_tag ) ) {
		$folder_tag = sanitize_text_field( $options['google_folder_id'] ?? '' );
	}

	$result = movisoft_dap_process_batch( $files, $offset, $limit, $folder_tag );

	if ( ! empty( $result['complete'] ) ) {
		delete_transient( 'movisoft_dap_drive_files_' . get_current_user_id() );
	}

	wp_send_json_success( $result );
}
add_action( 'wp_ajax_movisoft_dap_process_batch', 'movisoft_dap_ajax_process_batch' );
