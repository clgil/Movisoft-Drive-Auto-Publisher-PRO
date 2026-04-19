<?php
/**
 * Registro de menús administrativos.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Crea menús.
 */
function movisoft_dap_register_admin_menu(): void {
	add_menu_page(
		__( 'Movisoft Auto Publisher', 'movisoft-drive-auto-publisher' ),
		__( 'Movisoft Auto Publisher', 'movisoft-drive-auto-publisher' ),
		'manage_options',
		'movisoft-auto-publisher',
		'movisoft_dap_render_dashboard_page',
		'dashicons-database-import',
		60
	);

	add_submenu_page(
		'movisoft-auto-publisher',
		__( 'Dashboard', 'movisoft-drive-auto-publisher' ),
		__( 'Dashboard', 'movisoft-drive-auto-publisher' ),
		'manage_options',
		'movisoft-auto-publisher',
		'movisoft_dap_render_dashboard_page'
	);

	add_submenu_page(
		'movisoft-auto-publisher',
		__( 'Configuración API', 'movisoft-drive-auto-publisher' ),
		__( 'Configuración API', 'movisoft-drive-auto-publisher' ),
		'manage_options',
		'movisoft-auto-publisher-settings',
		'movisoft_dap_render_settings_page'
	);

	add_submenu_page(
		'movisoft-auto-publisher',
		__( 'Importar desde Drive', 'movisoft-drive-auto-publisher' ),
		__( 'Importar desde Drive', 'movisoft-drive-auto-publisher' ),
		'manage_options',
		'movisoft-auto-publisher-import',
		'movisoft_dap_render_import_page'
	);

	add_submenu_page(
		'movisoft-auto-publisher',
		__( 'Logs', 'movisoft-drive-auto-publisher' ),
		__( 'Logs', 'movisoft-drive-auto-publisher' ),
		'manage_options',
		'movisoft-auto-publisher-logs',
		'movisoft_dap_render_logs_page'
	);
}
add_action( 'admin_menu', 'movisoft_dap_register_admin_menu' );
