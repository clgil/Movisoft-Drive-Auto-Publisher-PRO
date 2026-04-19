<?php
/**
 * Generación y publicación de posts.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Obtiene contexto SEO desde nombre de archivo.
 *
 * @param string $file_name Nombre del archivo.
 * @param string $folder_hint Hint de marca.
 */
function movisoft_dap_extract_context( string $file_name, string $folder_hint = '' ): array {
	$clean_name = preg_replace( '/\.[^.\s]{2,5}$/', '', $file_name );
	$clean_name = is_string( $clean_name ) ? $clean_name : $file_name;
	$parts      = preg_split( '/[\s_\-.]+/', $clean_name );
	$parts      = is_array( $parts ) ? array_values( array_filter( $parts ) ) : array();

	$brand = ! empty( $folder_hint ) ? sanitize_text_field( $folder_hint ) : ( $parts[0] ?? 'Generic' );
	$model = $parts[1] ?? ( $parts[0] ?? 'Unknown Model' );

	preg_match( '/[A-Z0-9-]{5,}/', strtoupper( $clean_name ), $matches );
	$board_code = $matches[0] ?? strtoupper( substr( md5( $file_name ), 0, 8 ) );

	$type = 'schematic';
	if ( preg_match( '/boardview/i', $clean_name ) ) {
		$type = 'boardview';
	}

	return array(
		'brand'      => sanitize_text_field( $brand ),
		'model'      => sanitize_text_field( $model ),
		'board_code' => sanitize_text_field( $board_code ),
		'type'       => sanitize_text_field( $type ),
	);
}

/**
 * Crea un post a partir de archivo Drive.
 *
 * @param array  $file Datos de archivo.
 * @param string $folder_id Folder de drive.
 */
function movisoft_dap_create_post_from_file( array $file, string $folder_id ): array {
	$file_id   = sanitize_text_field( $file['id'] ?? '' );
	$file_name = sanitize_text_field( $file['name'] ?? '' );

	if ( empty( $file_id ) || empty( $file_name ) ) {
		return array(
			'success' => false,
			'error'   => 'Archivo inválido.',
		);
	}

	if ( movisoft_dap_is_duplicate( $file_id ) ) {
		movisoft_dap_log( "Duplicado detectado: {$file_name} ({$file_id})" );
		return array(
			'success'   => true,
			'duplicate' => true,
		);
	}

	$context = movisoft_dap_extract_context( $file_name, $folder_id );
	$title   = sprintf(
		'%s %s %s %s PDF Free Download',
		$context['brand'],
		$context['model'],
		$context['board_code'],
		ucfirst( $context['type'] )
	);

	$slug = sanitize_title( implode( '-', array( $context['brand'], $context['model'], $context['board_code'], $context['type'] ) ) );

	$original_url = 'https://drive.google.com/uc?export=download&id=' . rawurlencode( $file_id );
	$short_url    = movisoft_dap_shorten_url( $original_url );
	$ai_content   = movisoft_dap_generate_ai_content( $context );

	$content  = '<h2>Overview</h2>';
	$content .= $ai_content;
	$content .= '<h2>Common Issues</h2><ul>';
	$content .= '<li>No power</li><li>No charging</li><li>Short circuit</li><li>No display</li>';
	$content .= '</ul>';
	$content .= '<h2>Download</h2>';
	$content .= sprintf(
		'<a href="%1$s" target="_blank" rel="nofollow noopener" class="movisoft-download-btn">Download %2$s %3$s %4$s</a>',
		esc_url( $short_url ),
		esc_html( $context['brand'] ),
		esc_html( $context['model'] ),
		esc_html( $context['type'] )
	);

	$options     = get_option( 'movisoft_dap_settings', array() );
	$post_status = ! empty( $options['publish_mode'] ) && 'future' === $options['publish_mode'] ? 'future' : 'publish';
	$post_date   = current_time( 'mysql' );

	if ( 'future' === $post_status ) {
		$post_date = gmdate( 'Y-m-d H:i:s', time() + HOUR_IN_SECONDS );
	}

	$post_id = wp_insert_post(
		array(
			'post_title'   => wp_strip_all_tags( $title ),
			'post_name'    => $slug,
			'post_content' => wp_kses_post( $content ),
			'post_status'  => $post_status,
			'post_type'    => 'post',
			'post_date'    => $post_date,
		),
		true
	);

	if ( is_wp_error( $post_id ) ) {
		movisoft_dap_log( 'Error post: ' . $post_id->get_error_message() . ' Archivo: ' . $file_name );
		return array(
			'success' => false,
			'error'   => $post_id->get_error_message(),
		);
	}

	update_post_meta( $post_id, '_drive_file_id', $file_id );
	update_post_meta( $post_id, '_short_url', esc_url_raw( $short_url ) );
	update_post_meta( $post_id, '_original_drive_url', esc_url_raw( $original_url ) );

	$meta_description = sprintf(
		'Download %s %s %s %s schematic for motherboard repair technicians. Free PDF.',
		$context['brand'],
		$context['model'],
		$context['board_code'],
		$context['type']
	);
	$focus_kw         = sprintf( '%s %s %s schematic', $context['brand'], $context['model'], $context['board_code'] );

	update_post_meta( $post_id, '_wpseo_title', sanitize_text_field( $title ) );
	update_post_meta( $post_id, '_wpseo_metadesc', sanitize_text_field( $meta_description ) );
	update_post_meta( $post_id, '_wpseo_focuskw', sanitize_text_field( $focus_kw ) );

	$default_category = absint( $options['default_category'] ?? 0 );
	$default_tags     = sanitize_text_field( $options['default_tags'] ?? '' );
	$featured_image   = absint( $options['featured_image_id'] ?? 0 );

	if ( $default_category > 0 ) {
		wp_set_post_categories( $post_id, array( $default_category ), false );
	}

	if ( ! empty( $default_tags ) ) {
		$tags = array_map( 'trim', explode( ',', $default_tags ) );
		wp_set_post_tags( $post_id, $tags, false );
	}

	if ( $featured_image > 0 ) {
		set_post_thumbnail( $post_id, $featured_image );
	}

	movisoft_dap_log( "Post creado: {$post_id} desde archivo {$file_name} ({$file_id})" );

	return array(
		'success' => true,
		'post_id' => (int) $post_id,
	);
}

/**
 * Procesa lote de archivos.
 *
 * @param array  $files Lista de archivos.
 * @param int    $offset Offset.
 * @param int    $limit Límite.
 * @param string $folder_id Folder.
 */
function movisoft_dap_process_batch( array $files, int $offset, int $limit, string $folder_id ): array {
	$batch    = array_slice( $files, $offset, $limit );
	$results  = array();
	$processed = 0;

	foreach ( $batch as $file ) {
		$results[] = movisoft_dap_create_post_from_file( $file, $folder_id );
		++$processed;
	}

	$next_offset = $offset + $processed;

	return array(
		'processed'   => $processed,
		'next_offset' => $next_offset,
		'complete'    => $next_offset >= count( $files ),
		'total'       => count( $files ),
		'results'     => $results,
	);
}
