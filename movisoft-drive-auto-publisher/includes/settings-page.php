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
 * Sanitiza opciones.
 *
 * @param array $input Input settings.
 */
function movisoft_dap_sanitize_settings( array $input ): array {
	$current = get_option( 'movisoft_dap_settings', array() );
	$output  = is_array( $current ) ? $current : array();

	$output['google_folder_id'] = sanitize_text_field( $input['google_folder_id'] ?? '' );
	$output['folder_brand']     = sanitize_text_field( $input['folder_brand'] ?? '' );

	if ( ! empty( $_FILES['movisoft_google_credentials_json']['tmp_name'] ) ) {
		$file_name = sanitize_file_name( wp_unslash( $_FILES['movisoft_google_credentials_json']['name'] ?? '' ) );
		$tmp_path  = sanitize_text_field( wp_unslash( $_FILES['movisoft_google_credentials_json']['tmp_name'] ?? '' ) );

		$mime_check = movisoft_dap_validate_json_upload( $tmp_path, $file_name );
		if ( empty( $mime_check['success'] ) ) {
			add_settings_error( 'movisoft_dap_settings', 'movisoft_json_mime_error', sanitize_text_field( $mime_check['error'] ?? 'Archivo JSON inválido.' ) );
		} else {
			$parsed = movisoft_dap_parse_google_credentials_file( $tmp_path );
			if ( empty( $parsed['success'] ) ) {
				add_settings_error( 'movisoft_dap_settings', 'movisoft_json_parse_error', sanitize_text_field( $parsed['error'] ?? 'JSON inválido.' ) );
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
					add_settings_error( 'movisoft_dap_settings', 'movisoft_json_ok', 'Credenciales JSON cargadas correctamente.', 'updated' );
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
				<p><strong>Google OAuth:</strong> <?php echo esc_html( $state ); ?></p>
				<p><strong>Folder ID:</strong> <?php echo esc_html( $options['google_folder_id'] ?? '-' ); ?></p>
				<p><strong>IA:</strong> <?php echo ! empty( $options['openrouter_enabled'] ) ? esc_html__( 'Activa', 'movisoft-drive-auto-publisher' ) : esc_html__( 'Desactivada', 'movisoft-drive-auto-publisher' ); ?></p>
				<p><strong>Cron:</strong> <?php echo ! empty( $options['cron_enabled'] ) ? esc_html__( 'Activo (6h)', 'movisoft-drive-auto-publisher' ) : esc_html__( 'Inactivo', 'movisoft-drive-auto-publisher' ); ?></p>
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

	$options      = get_option( 'movisoft_dap_settings', array() );
	$connect_url  = wp_nonce_url( admin_url( 'admin-post.php?action=movisoft_dap_google_oauth_start' ), 'movisoft_dap_google_oauth_start' );
	$connected    = ! empty( $options['google_refresh_token'] );
	$has_json     = ! empty( $options['google_client_id'] ) && ! empty( $options['google_client_secret'] );
	$folders_data = $connected ? movisoft_dap_list_drive_folders() : array( 'success' => false, 'folders' => array() );
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
						<p class="description">Sube el OAuth 2.0 Client ID JSON de Google Cloud Console.</p>
						<?php if ( ! empty( $options['google_credentials_file'] ) ) : ?>
							<p><strong>Guardado:</strong> <?php echo esc_html( $options['google_credentials_file'] ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th>Conectar OAuth</th>
					<td>
						<?php if ( $has_json ) : ?>
							<a href="<?php echo esc_url( $connect_url ); ?>" class="button button-secondary">Conectar con Google</a>
							<p><strong>Estado:</strong> <?php echo $connected ? esc_html__( 'Conectado', 'movisoft-drive-auto-publisher' ) : esc_html__( 'No conectado', 'movisoft-drive-auto-publisher' ); ?></p>
						<?php else : ?>
							<p>Carga JSON para habilitar OAuth.</p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th><label for="google_folder_id">Carpeta raíz</label></th>
					<td>
						<select id="google_folder_id" name="movisoft_dap_settings[google_folder_id]" class="regular-text">
							<option value="">Selecciona carpeta</option>
							<?php foreach ( $folders_data['folders'] ?? array() as $folder ) : ?>
								<option value="<?php echo esc_attr( $folder['id'] ); ?>" <?php selected( $options['google_folder_id'] ?? '', $folder['id'] ); ?>><?php echo esc_html( $folder['name'] ); ?></option>
							<?php endforeach; ?>
						</select>
						<?php if ( empty( $folders_data['success'] ) && $connected ) : ?>
							<p class="description">No se pudieron cargar carpetas: <?php echo esc_html( $folders_data['error'] ?? 'Error desconocido' ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th><label for="folder_brand">Marca de carpeta</label></th>
					<td><input type="text" id="folder_brand" name="movisoft_dap_settings[folder_brand]" value="<?php echo esc_attr( $options['folder_brand'] ?? '' ); ?>" class="regular-text" /></td>
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
				<tr><th><label for="exeio_api_key">API Key</label></th><td><input type="password" id="exeio_api_key" name="movisoft_dap_settings[exeio_api_key]" value="<?php echo esc_attr( $options['exeio_api_key'] ?? '' ); ?>" class="regular-text" /></td></tr>
				<tr><th><label for="exeio_endpoint">Endpoint</label></th><td><input type="url" id="exeio_endpoint" name="movisoft_dap_settings[exeio_endpoint]" value="<?php echo esc_attr( $options['exeio_endpoint'] ?? 'https://exe.io/api' ); ?>" class="regular-text" /></td></tr>

				<tr><th colspan="2"><h2>OpenRouter</h2></th></tr>
				<tr><th><label for="openrouter_enabled">Activar IA</label></th><td><label><input type="checkbox" id="openrouter_enabled" name="movisoft_dap_settings[openrouter_enabled]" value="1" <?php checked( ! empty( $options['openrouter_enabled'] ) ); ?> /> Usar OpenRouter</label></td></tr>
				<tr><th><label for="openrouter_api_key">API Key</label></th><td><input type="password" id="openrouter_api_key" name="movisoft_dap_settings[openrouter_api_key]" value="<?php echo esc_attr( $options['openrouter_api_key'] ?? '' ); ?>" class="regular-text" /></td></tr>
				<tr><th><label for="openrouter_prompt">Prompt base</label></th><td><textarea id="openrouter_prompt" name="movisoft_dap_settings[openrouter_prompt]" class="large-text" rows="6"><?php echo esc_textarea( $options['openrouter_prompt'] ?? '' ); ?></textarea></td></tr>

				<tr><th colspan="2"><h2>Publicación</h2></th></tr>
				<tr><th><label for="publish_mode">Modo</label></th><td><select id="publish_mode" name="movisoft_dap_settings[publish_mode]"><option value="publish" <?php selected( $options['publish_mode'] ?? 'publish', 'publish' ); ?>>Publicar ahora</option><option value="future" <?php selected( $options['publish_mode'] ?? 'publish', 'future' ); ?>>Programar +1 hora</option></select></td></tr>
				<tr><th><label for="default_category">Categoría por defecto</label></th><td><?php wp_dropdown_categories( array( 'name' => 'movisoft_dap_settings[default_category]', 'id' => 'default_category', 'selected' => absint( $options['default_category'] ?? 0 ), 'show_option_none' => '— Ninguna —', 'hide_empty' => false ) ); ?></td></tr>
				<tr><th><label for="default_tags">Etiquetas por defecto</label></th><td><input type="text" id="default_tags" name="movisoft_dap_settings[default_tags]" value="<?php echo esc_attr( $options['default_tags'] ?? '' ); ?>" class="regular-text" /><p class="description">Separar por coma.</p></td></tr>
				<tr><th><label for="featured_image_id">Attachment ID imagen destacada</label></th><td><input type="number" id="featured_image_id" name="movisoft_dap_settings[featured_image_id]" value="<?php echo esc_attr( $options['featured_image_id'] ?? '' ); ?>" class="small-text" min="0" /></td></tr>
				<tr><th><label for="cron_enabled">Cron automático</label></th><td><label><input type="checkbox" id="cron_enabled" name="movisoft_dap_settings[cron_enabled]" value="1" <?php checked( ! empty( $options['cron_enabled'] ) ); ?> /> Ejecutar importación cada 6 horas</label></td></tr>
			</table>
			<?php submit_button( 'Guardar configuración' ); ?>
		</form>
	</div>
	<?php
}

/**
 * Importador manual.
 */
function movisoft_dap_render_import_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'No tienes permisos suficientes.', 'movisoft-drive-auto-publisher' ) );
	}

	$options   = get_option( 'movisoft_dap_settings', array() );
	$folder_id = sanitize_text_field( $options['google_folder_id'] ?? '' );
	$files     = array();
	$error     = '';

	if ( ! empty( $folder_id ) ) {
		$list = movisoft_dap_list_drive_files( $folder_id );
		if ( ! empty( $list['success'] ) ) {
			$files = $list['files'];
		} else {
			$error = sanitize_text_field( $list['error'] ?? 'No se pudieron cargar archivos.' );
		}
	}
	?>
	<div class="wrap movisoft-dap-wrap">
		<h1><?php echo esc_html__( 'Importar desde Drive', 'movisoft-drive-auto-publisher' ); ?></h1>
		<?php if ( ! empty( $error ) ) : ?><p><strong><?php echo esc_html( $error ); ?></strong></p><?php endif; ?>
		<?php if ( empty( $folder_id ) ) : ?>
			<p>Configura y selecciona una carpeta en Configuración API.</p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><input type="checkbox" id="movisoft-check-all" /></th>
						<th>Nombre</th>
						<th>Tamaño</th>
						<th>Tipo</th>
						<th>Estado</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $files as $file ) : ?>
						<tr>
							<td><input type="checkbox" class="movisoft-file-check" value="<?php echo esc_attr( $file['id'] ); ?>" <?php disabled( ! empty( $file['published'] ) ); ?> /></td>
							<td><?php echo esc_html( $file['name'] ); ?></td>
							<td><?php echo esc_html( size_format( absint( $file['size'] ) ) ); ?></td>
							<td><?php echo esc_html( $file['mimeType'] ); ?></td>
							<td><?php echo ! empty( $file['published'] ) ? esc_html__( 'Publicado', 'movisoft-drive-auto-publisher' ) : esc_html__( 'No publicado', 'movisoft-drive-auto-publisher' ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p><button class="button button-primary" id="movisoft-start-import">Importar seleccionados</button></p>
			<div id="movisoft-import-status" class="movisoft-status"></div>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Logs page.
 */
function movisoft_dap_render_logs_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'No tienes permisos suficientes.', 'movisoft-drive-auto-publisher' ) );
	}
	?>
	<div class="wrap movisoft-dap-wrap">
		<h1><?php echo esc_html__( 'Logs', 'movisoft-drive-auto-publisher' ); ?></h1>
		<textarea class="large-text code" rows="24" readonly><?php echo esc_textarea( movisoft_dap_read_log() ); ?></textarea>
	</div>
	<?php
}

/**
 * Test de conexión.
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

	wp_safe_redirect( add_query_arg( array( 'page' => 'movisoft-auto-publisher-settings', 'movisoft_drive_test' => $status ), admin_url( 'admin.php' ) ) );
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

	$selected_ids = isset( $_POST['selected_ids'] ) && is_array( $_POST['selected_ids'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['selected_ids'] ) ) : array();
	$options      = get_option( 'movisoft_dap_settings', array() );
	$folder_id    = sanitize_text_field( $options['google_folder_id'] ?? '' );

	if ( empty( $folder_id ) ) {
		wp_send_json_error( array( 'message' => 'Folder ID no configurado.' ) );
	}

	$list = movisoft_dap_list_drive_files( $folder_id );
	if ( empty( $list['success'] ) ) {
		wp_send_json_error( array( 'message' => $list['error'] ?? 'Error listando archivos.' ) );
	}

	$files = $list['files'];
	if ( ! empty( $selected_ids ) ) {
		$files = array_values(
			array_filter(
				$files,
				static function ( array $file ) use ( $selected_ids ): bool {
					return in_array( $file['id'], $selected_ids, true );
				}
			)
		);
	}

	set_transient( 'movisoft_dap_drive_files_' . get_current_user_id(), $files, 20 * MINUTE_IN_SECONDS );
	wp_send_json_success( array( 'total' => count( $files ) ) );
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
	$files  = get_transient( 'movisoft_dap_drive_files_' . get_current_user_id() );
	if ( ! is_array( $files ) ) {
		wp_send_json_error( array( 'message' => 'Sesión de importación expirada.' ) );
	}

	$options    = get_option( 'movisoft_dap_settings', array() );
	$folder_tag = sanitize_text_field( $options['folder_brand'] ?? '' );
	if ( empty( $folder_tag ) ) {
		$folder_tag = sanitize_text_field( $options['google_folder_id'] ?? '' );
	}

	$result = movisoft_dap_process_batch( $files, $offset, 10, $folder_tag );
	if ( ! empty( $result['complete'] ) ) {
		delete_transient( 'movisoft_dap_drive_files_' . get_current_user_id() );
	}

	wp_send_json_success( $result );
}
add_action( 'wp_ajax_movisoft_dap_process_batch', 'movisoft_dap_ajax_process_batch' );
