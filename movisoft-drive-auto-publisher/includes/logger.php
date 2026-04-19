<?php
/**
 * Logger del plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Obtiene path del archivo log.
 */
function movisoft_dap_get_log_path(): string {
	$upload_dir = wp_upload_dir();
	return trailingslashit( $upload_dir['basedir'] ) . 'movisoft-import-log.txt';
}

/**
 * Escribe una línea al log.
 *
 * @param string $message Mensaje.
 */
function movisoft_dap_log( string $message ): void {
	$line = sprintf( "[%s] %s\n", gmdate( 'Y-m-d H:i:s' ), $message );
	$file = movisoft_dap_get_log_path();
	wp_mkdir_p( dirname( $file ) );
	file_put_contents( $file, $line, FILE_APPEND | LOCK_EX );
}

/**
 * Lee contenido del log.
 */
function movisoft_dap_read_log(): string {
	$file = movisoft_dap_get_log_path();
	if ( ! file_exists( $file ) ) {
		return '';
	}
	$content = file_get_contents( $file );
	return is_string( $content ) ? $content : '';
}
