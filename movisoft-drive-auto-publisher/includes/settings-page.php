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
	$output = array();

	$output['google_client_id']     = sanitize_text_field( $input['google_client_id'] ?? '' );
	$output['google_client_secret'] = sanitize_text_field( $input['google_client_secret'] ?? '' );
	$output['google_refresh_token'] = sanitize_text_field( $input['google_refresh_token'] ?? '' );
	$output['google_folder_id']     = sanitize_text_field( $input['google_folder_id'] ?? '' );
	$output['folder_brand']         = sanitize_text_field( $input['folder_brand'] ?? '' );

	$output['exeio_api_key']  = sanitize_text_field( $input['exeio_api_key'] ?? '' );
	$output['exeio_endpoint'] = esc_url_raw( $input['exeio_endpoint'] ?? 'https://exe.io/api' );

	$output['openrouter_enabled'] = ! empty( $input['openrouter_enabled'] ) ? 1 : 0;
	$output['openrouter_api_key'] = sanitize_text_field( $input['openrouter_api_key'] ?? '' );
	$output['openrouter_prompt']  = wp_kses_post( $input['openrouter_prompt'] ?? '' );

	$output['default_category'] = absint( $input['default_category'] ?? 0 );
	$output['default_tags']     = sanitize_text_field( $input['default_tags'] ?? '' );
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
	?>
	<div class="wrap movisoft-dap-wrap">
		<h1><?php echo esc_html__( 'Movisoft Drive Auto Publisher PRO', 'movisoft-drive-auto-publisher' ); ?></h1>
		<div class="movisoft-grid">
			<div class="movisoft-card">
				<h2><?php echo esc_html__( 'Estado', 'movisoft-drive-auto-publisher' ); ?></h2>
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

	$options = get_option( 'movisoft_dap_settings', array() );
	?>
	<div class="wrap movisoft-dap-wrap">
		<h1><?php echo esc_html__( 'Configuración API', 'movisoft-drive-auto-publisher' ); ?></h1>
		<form method="post" action="options.php">
			<?php settings_fields( 'movisoft_dap_settings_group' ); ?>
			<?php wp_nonce_field( 'movisoft_dap_settings_nonce', 'movisoft_dap_settings_nonce_field' ); ?>
			<table class="form-table" role="presentation">
				<tr><th colspan="2"><h2>Google Drive API v3</h2></th></tr>
				<tr>
					<th><label for="google_client_id">Client ID</label></th>
					<td><input type="text" id="google_client_id" name="movisoft_dap_settings[google_client_id]" value="<?php echo esc_attr( $options['google_client_id'] ?? '' ); ?>" class="regular-text" /></td>
				</tr>
				<tr>
					<th><label for="google_client_secret">Client Secret</label></th>
					<td><input type="password" id="google_client_secret" name="movisoft_dap_settings[google_client_secret]" value="<?php echo esc_attr( $options['google_client_secret'] ?? '' ); ?>" class="regular-text" /></td>
				</tr>
				<tr>
					<th><label for="google_refresh_token">Refresh Token</label></th>
					<td><input type="password" id="google_refresh_token" name="movisoft_dap_settings[google_refresh_token]" value="<?php echo esc_attr( $options['google_refresh_token'] ?? '' ); ?>" class="regular-text" /></td>
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
 * Importador manual.
 */
function movisoft_dap_render_import_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'No tienes permisos suficientes.', 'movisoft-drive-auto-publisher' ) );
	}
	?>
	<div class="wrap movisoft-dap-wrap">
		<h1><?php echo esc_html__( 'Importar desde Drive', 'movisoft-drive-auto-publisher' ); ?></h1>
		<p><?php echo esc_html__( 'Procesa archivos en lotes de 10 para evitar timeout.', 'movisoft-drive-auto-publisher' ); ?></p>
		<button class="button button-primary" id="movisoft-start-import"><?php echo esc_html__( 'Importar', 'movisoft-drive-auto-publisher' ); ?></button>
		<div id="movisoft-import-status" class="movisoft-status"></div>
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

	$log_content = movisoft_dap_read_log();
	?>
	<div class="wrap movisoft-dap-wrap">
		<h1><?php echo esc_html__( 'Logs', 'movisoft-drive-auto-publisher' ); ?></h1>
		<textarea class="large-text code" rows="24" readonly><?php echo esc_textarea( $log_content ); ?></textarea>
	</div>
	<?php
}

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

	$options   = get_option( 'movisoft_dap_settings', array() );
	$folder_id = sanitize_text_field( $options['folder_brand'] ?? '' );
	if ( empty( $folder_id ) ) {
		$folder_id = sanitize_text_field( $options['google_folder_id'] ?? '' );
	}

	$result = movisoft_dap_process_batch( $files, $offset, $limit, $folder_id );

	if ( ! empty( $result['complete'] ) ) {
		delete_transient( 'movisoft_dap_drive_files_' . get_current_user_id() );
	}

	wp_send_json_success( $result );
}
add_action( 'wp_ajax_movisoft_dap_process_batch', 'movisoft_dap_ajax_process_batch' );
