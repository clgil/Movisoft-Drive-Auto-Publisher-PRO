<?php
/**
 * Integración OpenRouter.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Genera contenido SEO con IA.
 *
 * @param array $context Contexto del archivo.
 */
function movisoft_dap_generate_ai_content( array $context ): string {
	$options    = get_option( 'movisoft_dap_settings', array() );
	$enable_ai  = ! empty( $options['openrouter_enabled'] );
	$api_key    = sanitize_text_field( $options['openrouter_api_key'] ?? '' );
	$base_prompt = wp_kses_post( $options['openrouter_prompt'] ?? '' );

	if ( ! $enable_ai || empty( $api_key ) ) {
		return movisoft_dap_fallback_content( $context );
	}

	$prompt = ! empty( $base_prompt ) ? $base_prompt : 'Escribe un artículo técnico SEO de mínimo 600 palabras para reparadores de motherboards, en español, con tono profesional y consejos prácticos.';
	$prompt .= "\n\nDatos:\n";
	$prompt .= 'Marca: ' . sanitize_text_field( $context['brand'] ?? '' ) . "\n";
	$prompt .= 'Modelo: ' . sanitize_text_field( $context['model'] ?? '' ) . "\n";
	$prompt .= 'Board Code: ' . sanitize_text_field( $context['board_code'] ?? '' ) . "\n";
	$prompt .= 'Tipo: ' . sanitize_text_field( $context['type'] ?? '' ) . "\n";

	$response = wp_remote_post(
		'https://openrouter.ai/api/v1/chat/completions',
		array(
			'timeout' => 50,
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $api_key,
			),
			'body'    => wp_json_encode(
				array(
					'model'    => 'openai/gpt-oss-120b:free',
					'messages' => array(
						array(
							'role'    => 'user',
							'content' => $prompt,
						),
					),
				),
			),
		)
	);

	if ( is_wp_error( $response ) ) {
		movisoft_dap_log( 'Error IA: ' . $response->get_error_message() );
		return movisoft_dap_fallback_content( $context );
	}

	$code = wp_remote_retrieve_response_code( $response );
	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	$content = $body['choices'][0]['message']['content'] ?? '';
	if ( 200 !== $code || empty( $content ) ) {
		movisoft_dap_log( 'Error IA: respuesta inválida.' );
		return movisoft_dap_fallback_content( $context );
	}

	return wp_kses_post( wpautop( $content ) );
}

/**
 * Contenido fallback sin IA.
 *
 * @param array $context Contexto.
 */
function movisoft_dap_fallback_content( array $context ): string {
	$brand = sanitize_text_field( $context['brand'] ?? '' );
	$model = sanitize_text_field( $context['model'] ?? '' );
	$board = sanitize_text_field( $context['board_code'] ?? '' );
	$type  = sanitize_text_field( $context['type'] ?? '' );

	$text  = sprintf( 'Este recurso técnico de %s %s %s (%s) está orientado a técnicos reparadores que requieren diagnóstico preciso de placa. ', $brand, $model, $board, $type );
	$text .= 'Incluye recomendaciones de inspección visual, validaciones de voltajes primarios y secundarios, y revisión de líneas críticas de arranque. ';
	$text .= 'Además, se sugieren prácticas para localizar cortocircuitos, identificar componentes en falla y optimizar tiempos de reparación con metodología profesional. ';
	$text .= 'El uso de este material puede mejorar la tasa de éxito en trabajos de laboratorio y reducir retrabajos, especialmente en equipos con síntomas intermitentes. ';
	$text .= 'Se recomienda documentar cada medición y comparar contra referencias del board para una reparación consistente y segura.';

	return wp_kses_post( wpautop( str_repeat( $text, 4 ) ) );
}
