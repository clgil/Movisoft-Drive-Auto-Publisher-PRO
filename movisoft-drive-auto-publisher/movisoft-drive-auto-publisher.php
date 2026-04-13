<?php
/**
 * Plugin Name: Movisoft Drive Auto Publisher PRO
 * Description: Automatiza la creación de posts desde Google Drive con enlaces cortos, IA y SEO técnico.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: Movisoft
 * Text Domain: movisoft-drive-auto-publisher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MOVISOFT_DAP_VERSION', '1.0.0' );
define( 'MOVISOFT_DAP_FILE', __FILE__ );
define( 'MOVISOFT_DAP_PATH', plugin_dir_path( __FILE__ ) );
define( 'MOVISOFT_DAP_URL', plugin_dir_url( __FILE__ ) );

require_once MOVISOFT_DAP_PATH . 'includes/logger.php';
require_once MOVISOFT_DAP_PATH . 'includes/drive-api.php';
require_once MOVISOFT_DAP_PATH . 'includes/exeio-api.php';
require_once MOVISOFT_DAP_PATH . 'includes/openrouter-api.php';
require_once MOVISOFT_DAP_PATH . 'includes/duplicate-checker.php';
require_once MOVISOFT_DAP_PATH . 'includes/post-generator.php';
require_once MOVISOFT_DAP_PATH . 'includes/cron.php';
require_once MOVISOFT_DAP_PATH . 'includes/settings-page.php';
require_once MOVISOFT_DAP_PATH . 'includes/admin-menu.php';

register_activation_hook( __FILE__, 'movisoft_dap_activate_plugin' );
register_deactivation_hook( __FILE__, 'movisoft_dap_deactivate_plugin' );

/**
 * Activa tareas iniciales del plugin.
 */
function movisoft_dap_activate_plugin(): void {
	movisoft_dap_register_cron_schedule();
	movisoft_dap_maybe_schedule_cron();
	flush_rewrite_rules();
}

/**
 * Limpia cron al desactivar.
 */
function movisoft_dap_deactivate_plugin(): void {
	$timestamp = wp_next_scheduled( 'movisoft_drive_auto_import' );
	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, 'movisoft_drive_auto_import' );
	}
	flush_rewrite_rules();
}

/**
 * Carga assets de administración.
 *
 * @param string $hook Hook actual.
 */
function movisoft_dap_admin_assets( string $hook ): void {
	if ( false === strpos( $hook, 'movisoft-auto-publisher' ) ) {
		return;
	}

	wp_enqueue_style( 'movisoft-dap-admin', MOVISOFT_DAP_URL . 'assets/css/admin.css', array(), MOVISOFT_DAP_VERSION );
	wp_enqueue_script( 'movisoft-dap-import', MOVISOFT_DAP_URL . 'assets/js/import.js', array( 'jquery' ), MOVISOFT_DAP_VERSION, true );

	wp_localize_script(
		'movisoft-dap-import',
		'movisoftDAP',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'movisoft_dap_import_nonce' ),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'movisoft_dap_admin_assets' );

/**
 * Carga css frontend para botón de descarga.
 */
function movisoft_dap_frontend_assets(): void {
	wp_enqueue_style( 'movisoft-dap-frontend', MOVISOFT_DAP_URL . 'assets/css/frontend.css', array(), MOVISOFT_DAP_VERSION );
}
add_action( 'wp_enqueue_scripts', 'movisoft_dap_frontend_assets' );
