<?php
/**
 * Control de duplicados.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Verifica si un archivo de Drive ya fue publicado.
 *
 * @param string $drive_file_id ID del archivo.
 */
function movisoft_dap_is_duplicate( string $drive_file_id ): bool {
	$query = new WP_Query(
		array(
			'post_type'      => 'post',
			'post_status'    => array( 'publish', 'future', 'draft', 'pending', 'private' ),
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'   => '_drive_file_id',
					'value' => sanitize_text_field( $drive_file_id ),
				),
			),
		)
	);

	return $query->have_posts();
}
