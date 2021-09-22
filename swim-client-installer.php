<?php
define( 'SWIM_CLIENT_INSTALLER_VERSION', '1.1.5' );

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
	echo "client script already exists: $client_script" . PHP_EOL;
	// maybe update?

	// echo "update process not implemented yet..." . PHP_EOL;
	// todo compare version: https://developer.wordpress.org/reference/functions/get_plugin_data/
	// todo if remote version > local version
	// todo delete local file

	@unlink( $client_script );
}

// install
if ( ! file_exists( $client_script ) ) {
	echo "client script does not exist: $client_script" . PHP_EOL;

	echo "install client script..." . PHP_EOL;
	file_put_contents( $client_script, $client_script_content );

	// add simple support for CGI/FastCGI
	// @see https://support.tigertech.net/php-http-auth
	echo "install .htaccess..." . PHP_EOL;
	file_put_contents( SWIM_CLIENT_PATH . '/.htaccess', 'CGIPassAuth On' );
}

// house keeping
if ( file_exists( $public_html_dir . '/.swim-client' ) ) {
	echo "found old installation, clean..." . PHP_EOL;
	system( 'rm -rf -- ' . escapeshellarg( $public_html_dir . '/.swim-client' ) );
}
