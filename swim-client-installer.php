<?php
define( 'SWIM_CLIENT_INSTALLER_VERSION', '1.1.0' );

// https://stackoverflow.com/a/30926828/1160173
$home_dir        = posix_getpwuid( getmyuid() )['dir'];
$public_html_dir = $home_dir . '/public_html';

// don't use hidden folders here (es. ".swim-client")
// this is not working under certain server configurations (GreenGeeks Reseller)
// https://my.greengeeks.com/support/ticket/AHC-631-74560
define( 'SWIM_CLIENT_PATH', $public_html_dir . '/cgi-swim' );

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

	// add simple support for CGI/FastCGI
	// @see https://support.tigertech.net/php-http-auth
	file_put_contents( SWIM_CLIENT_PATH . '/.htaccess', 'CGIPassAuth On' );
}

// house keeping
if ( file_exists( $public_html_dir . '/.swim-client' ) ) {
	@unlink( $public_html_dir . '/.swim-client' );
}
