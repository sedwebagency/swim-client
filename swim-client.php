<?php
define( 'SWIM_CLIENT_VERSION', '1.0.0' );
define( 'SWIM_CLIENT_DIR', __DIR__ );
define( 'SWIM_CLIENT_SECRET_FILE', SWIM_CLIENT_DIR . '/.swim-client-secret' );

// get the secret data
$swim_client_secret_data = [];
if ( file_exists( SWIM_CLIENT_SECRET_FILE ) ) {
	$swim_client_secret_data = json_decode( file_get_contents( SWIM_CLIENT_SECRET_FILE ), true );
}

// if no secret data
if ( empty( $swim_client_secret_data ) ) {
	// create secret data
	$swim_client_secret_data = array(
		'swim_client_version' => SWIM_CLIENT_VERSION,
		'remote_host'         => get_real_ip_address(),

		// random secret
		'secret'              => bin2hex( openssl_random_pseudo_bytes( 20 ) )
	);

	// save secret data
	file_put_contents( SWIM_CLIENT_SECRET_FILE, json_encode( $swim_client_secret_data ) );

	// send reply with secret data
	send_json_reply( $swim_client_secret_data );
}

// check request auth
$secret = isset( $_REQUEST['secret'] ) ? $_REQUEST['secret'] : '';
if ( $secret !== $swim_client_secret_data['secret'] ) {
	send_json_reply( [ 'error' => 'Unauthorized.' ], false );
}

// parse request
$action = isset( $_REQUEST['action'] ) ? $_REQUEST['action'] : '';
switch ( $action ) {
	default:
		$data = array();
}


/**
 * @param array $data
 */
function send_json_reply( $data, $success = true ) {
	$output = array_merge( $data, array(
		'swim_client_version' => SWIM_CLIENT_VERSION,
		'success'             => $success,
	) );

	header( 'Content-Type: application/json' );
	echo json_encode( $output, JSON_PRETTY_PRINT );
	exit;
}

/**
 * @return string
 */
function get_real_ip_address() {
	if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
		// whether ip is from the share internet  
		$ip = $_SERVER['HTTP_CLIENT_IP'];
	} else if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
		// whether ip is from the proxy
		$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
	} else if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
		// whether ip is from the remote address
		$ip = $_SERVER['REMOTE_ADDR'];
	} else {
		// fallback
		$ip = '127.0.0.1';
	}

	return $ip;
}  
