<?php
define( 'SWIM_CLIENT_INSTALLER_VERSION', '1.0.0' );

// https://stackoverflow.com/a/30926828/1160173
$home_dir = posix_getpwuid( getmyuid() )['dir'];
define( 'SWIM_CLIENT_PATH', $home_dir . '/swim-client' );

define( 'SWIM_CLIENT_NAME', 'swim-client.php' );
define( 'SWIM_CLIENT_SCRIPT', 'https://raw.githubusercontent.com/sedwebagency/swim-client/master/swim-client.php' );

if ( ! file_exists( SWIM_CLIENT_PATH ) ) {
	@mkdir( SWIM_CLIENT_PATH );
}

$client_script = SWIM_CLIENT_PATH . '/' . SWIM_CLIENT_NAME;
if ( ! file_exists( $client_script ) ) {
	$client_script_content = @file_get_contents( SWIM_CLIENT_SCRIPT );
	file_put_contents( $client_script, $client_script_content );
}
