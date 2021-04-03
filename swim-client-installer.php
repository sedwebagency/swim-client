<?php
define( 'SWIM_CLIENT_INSTALLER_VERSION', '1.0.0' );

// https://stackoverflow.com/a/30926828/1160173
$home_dir        = posix_getpwuid( getmyuid() )['dir'];
$public_html_dir = $home_dir . '/public_html';
define( 'SWIM_CLIENT_PATH', $public_html_dir . '/.swim-client' );

define( 'SWIM_CLIENT_NAME', 'swim-client.php' );
define( 'SWIM_CLIENT_SCRIPT', 'https://raw.githubusercontent.com/sedwebagency/swim-client/master/swim-client.php' );

if ( ! file_exists( SWIM_CLIENT_PATH ) ) {
	@mkdir( SWIM_CLIENT_PATH );
}

// remote phar script content
$client_script_content = @file_get_contents( SWIM_CLIENT_SCRIPT );

// local phar script
$client_script = SWIM_CLIENT_PATH . '/' . SWIM_CLIENT_NAME;

// check local version
if ( file_exists( $client_script ) ) {
	// maybe update?

	// todo compare version: https://developer.wordpress.org/reference/functions/get_plugin_data/
	// todo if remote version > local version
	// todo delete local file
}

// install
if ( ! file_exists( $client_script ) ) {
	file_put_contents( $client_script, $client_script_content );

	// add support for CGI/FastCGI
	file_put_contents(
		SWIM_CLIENT_PATH . '/.htaccess',
		'SetEnvIfNoCase ^Authorization$ "(.+)" PHP_AUTH_DIGEST_RAW=$1'
	);
}
