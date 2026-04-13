<?php
/**
 * Tareas automáticas WP-Cron.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registra frecuencia de 6 horas.
 *
 * @param array $schedules Schedules.
 */
function movisoft_dap_register_cron_schedule( array $schedules = array() ): array {
	$schedules['movisoft_every_6_hours'] = array(
		'interval' => 6 * HOUR_IN_SECONDS,
		'display'  => __( 'Cada 6 horas (Movisoft)', 'movisoft-drive-auto-publisher' ),
	);
	return $schedules;
}
add_filter( 'cron_schedules', 'movisoft_dap_register_cron_schedule' );

/**
 * Programa cron si está habilitado.
 */
function movisoft_dap_maybe_schedule_cron(): void {
	$options      = get_option( 'movisoft_dap_settings', array() );
	$cron_enabled = ! empty( $options['cron_enabled'] );
	$timestamp    = wp_next_scheduled( 'movisoft_drive_auto_import' );

	if ( $cron_enabled && ! $timestamp ) {
		wp_schedule_event( time() + 120, 'movisoft_every_6_hours', 'movisoft_drive_auto_import' );
	}

	if ( ! $cron_enabled && $timestamp ) {
		wp_unschedule_event( $timestamp, 'movisoft_drive_auto_import' );
	}
}

/**
 * Handler del cron.
 */
function movisoft_dap_run_cron_import(): void {
	$options   = get_option( 'movisoft_dap_settings', array() );
	$folder_id = sanitize_text_field( $options['google_folder_id'] ?? '' );
	if ( empty( $folder_id ) ) {
		movisoft_dap_log( 'Cron detenido: Folder ID no configurado.' );
		return;
	}

	$list = movisoft_dap_list_drive_files( $folder_id );
	if ( empty( $list['success'] ) ) {
		movisoft_dap_log( 'Cron error listando archivos: ' . ( $list['error'] ?? 'Error desconocido' ) );
		return;
	}

	foreach ( $list['files'] as $file ) {
		movisoft_dap_create_post_from_file( $file, $folder_id );
	}
}
add_action( 'movisoft_drive_auto_import', 'movisoft_dap_run_cron_import' );

add_action(
	'update_option_movisoft_dap_settings',
	function (): void {
		movisoft_dap_maybe_schedule_cron();
	},
	10,
	0
);
