<?php
/**
 * OAuth2 Google Drive.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Inicia OAuth con Google.
 */
function movisoft_dap_handle_google_oauth_start(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'No autorizado.', 'movisoft-drive-auto-publisher' ) );
	}

	check_admin_referer( 'movisoft_dap_google_oauth_start' );

	$options   = get_option( 'movisoft_dap_settings', array() );
	$auth_uri  = esc_url_raw( $options['google_auth_uri'] ?? 'https://accounts.google.com/o/oauth2/auth' );
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
		movisoft_dap_log( 'OAuth cancelado: ' . sanitize_text_field( wp_unslash( $_GET['error'] ) ) );
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
