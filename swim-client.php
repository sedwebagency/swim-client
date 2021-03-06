<?php
/**
 * if WHM is available use port 2087 and WHM credentials; the unix function
 * get_current_user() will detect the cPanel account thanks to the called domain name
 *      if AutoSSL is available
 *          - https://sedwebdemo.srv3.sedweb.it/.swim-client/swim-client.php?cpanel_port=2087&action=databases
 *      if AutoSSL is not available
 *          - http://accapo.hippi1.sedweb.it/.swim-client/swim-client.php?cpanel_port=2087&action=databases
 *
 * if WHM is not available (cPanel only) use port 2083 and cPanel credentials
 *      - https://srv-dev.sedwebtools.it/.swim-client/swim-client.php?cpanel_port=2083&action=databases
 */
/**
 * todo add headers: https://developer.wordpress.org/reference/functions/get_plugin_data/
 * todo use phar https://blog.programster.org/creating-phar-files
 *
 * todo use softaculous cli /usr/local/cpanel/3rdparty/bin/php /usr/local/cpanel/whostmgr/docroot/cgi/softaculous/cli.php  --list_ins --resp=json
 */
header( 'Cache-Control: no-cache, must-revalidate, max-age=0' );

define( 'SWIM_CLIENT_VERSION', '1.4.6' );
define( 'SWIM_CLIENT_DIR', __DIR__ );

define( 'SWIM_DEBUG', isset( $_REQUEST['swim_debug'] ) && $_REQUEST['swim_debug'] == 1 );

$home_dir = posix_getpwuid( getmyuid() )['dir'];
define( 'SOFTACULOUS_DIR', $home_dir . '/.softaculous' );

// maybe enable debug
if ( SWIM_DEBUG ) {
	@ini_set( 'display_errors', 1 );
	@ini_set( 'log_errors', 1 );
	error_reporting( E_ALL ^ E_NOTICE );
}

// check request auth
// @see https://gist.github.com/rchrd2/c94eb4701da57ce9a0ad4d2b00794131
if ( empty( $_SERVER['PHP_AUTH_USER'] ) || empty( $_SERVER['PHP_AUTH_PW'] ) ) {
	send_http_unauthorized();
}

// cpanel instance
$host = isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : gethostname();
// WHM port when WHM is available (usually 2087)
// cPanel port when WHM is not available (usually 2083)
// Please note: cpanel_jsonapi_user is ignored when using cPanel port
$port = isset( $_REQUEST['cpanel_port'] ) ? intval( $_REQUEST['cpanel_port'] ) : 2083;

$cpanel = new Cpanel( $host, $port );
$cpanel->loginBasic( $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'] );
try {
	$addondomains = $cpanel->AddonDomain_listaddondomains( get_current_user() );
} catch ( Exception $e ) {
	send_http_unauthorized();
}

// logged in, now ready to prepare the response
$data = array();

$data['system'] = array(
	'bin_php'     => get_bin_php(),
	'bin_php_sys' => PHP_BINARY,
	'bin_wp'      => get_bin_wp(),
);

$data['installations'] = array();

// todo use softaculous api /usr/local/cpanel/3rdparty/bin/php /usr/local/cpanel/whostmgr/docroot/cgi/softaculous/cli.php  --list_ins --resp=json
$softaculous_installations = SOFTACULOUS_DIR . '/installations.php';
if ( file_exists( $softaculous_installations ) ) {
	// get all softaculous known installations
	$installations = @unserialize( @file_get_contents( $softaculous_installations ) );

	// sort by domain name
	usort( $installations, function ( $a, $b ) {
		return strcasecmp( $a['softdomain'], $b['softdomain'] );
	} );

	// create the response structure

	foreach ( $installations as $installation ) {
		$item = $installation;

		// WordPress only (WordPress SID = 26)
		if ( 26 === intval( $installation['sid'] ) ) {
			$item['wp'] = array();

			// sedweb-service version
			$item['wp']['sedweb_service_ver'] = wpcli_exec( 'plugin get sedweb-service --field=version --skip-themes --skip-plugins', $installation['softpath'] );

			// is infected
			$item['wp']['wp_is_infected'] = file_exists( $installation['softpath'] . '/upl.php' );
		}

		$data['installations'][ $installation['insid'] ] = $item;
	}
}

$data['domains'] = array();

// already got the addon domains when checking the user auth
foreach ( $addondomains as $addondomain ) {
	$data['domains'][] = array(
		'domain'    => $addondomain->domain,
		'site_path' => $addondomain->dir,
		'site_size' => folderSize( $addondomain->dir ),
	);
}

// parse request
$action = isset( $_REQUEST['action'] ) ? $_REQUEST['action'] : '';
switch ( $action ) {
	case 'databases':
		$databases = $cpanel->MysqlFE_listdbs( get_current_user() );

		$data['databases'] = array();
		foreach ( $databases as $database ) {
			$data['databases'][] = array(
				'db_name'    => $database->db,
				'db_users'   => array_pluck( $database->userlist, 'user' ),
				'db_size'    => $database->size,
				'db_sizemeg' => $database->sizemeg,
			);
		}

		// now search for known frameworks
		// in public_html and addon domains folders
		// and complete database credentials as possible
		break;

	default:
}

if ( ! headers_sent() ) {
	header( 'Content-Type: application/json; charset=utf-8' );
}

send_json_reply( $data, true );
exit;


/**
 * cPanel API Docs
 * @see https://documentation.cpanel.net/display/var_dump/Use+WHM+API+to+Call+cPanel+API+and+UAPI
 *
 * MysqlFE (deprecated)
 * @see http://documentation.cpanel.net:8090/display/var_dump/cPanel+API+2+-+Deprecated+cPanel+Tag+Usage
 */
class Cpanel {
	protected $host;
	protected $port;

	protected $loginType;
	protected $user;
	protected $pass;
	protected $token;

	/**
	 * Use 2087 if you have WHM access
	 * otherwise use 2083 (cPanel only)
	 *
	 * @param string $host
	 * @param int $port
	 */
	public function __construct( $host, $port = 2087 ) {
		$this->host = $host;
		$this->port = $port;
	}

	/**
	 * When you have cPanel username and password
	 *
	 * @param $user
	 * @param $pass
	 */
	public function loginBasic( $user, $pass ) {
		$this->loginType = 'auth_basic';
		$this->user      = $user;
		$this->pass      = $pass;
	}

	/**
	 * When you have cPanel/WHM token
	 *
	 * @param $user
	 * @param $token
	 */
	public function loginApiToken( $user, $token ) {
		$this->loginType = 'api_token';
		$this->user      = $user;
		$this->token     = $token;
	}

	/**
	 * @param string $account
	 *
	 * @return array
	 * @throws Exception
	 */
	public function AddonDomain_listaddondomains( $account ) {
		$url = $this->buildCpanelUrlV2( $account, 'AddonDomain', 'listaddondomains' );
		$res = $this->executeCallCpanel( $url );

		usort( $res, function ( $a, $b ) {
			return strcasecmp( $a->domain, $b->domain );
		} );

		return $res;
	}

	/**
	 * @param string $account
	 *
	 * @return array
	 * @throws Exception
	 */
	public function MysqlFE_listdbs( $account ) {
		$url = $this->buildCpanelUrlV2( $account, 'MysqlFE', 'listdbs' );

		return $this->executeCallCpanel( $url );
	}

	/**
	 * @param string $account
	 *
	 * @return array
	 * @throws Exception
	 */
	public function MysqlFE_listusers( $account ) {
		$url = $this->buildCpanelUrlV2( $account, 'MysqlFE', 'listusers' );

		return $this->executeCallCpanel( $url );
	}

	private function buildCpanelUrlV2( $user, $module, $command, $opts = [] ) {
		// todo forse qui dovrei usare il protocol usato dal browser, non forzarlo
		$url = "https://{$this->host}:{$this->port}/json-api/cpanel?";
		$url .= "&cpanel_jsonapi_user=$user";
		$url .= "&cpanel_jsonapi_module=$module";
		$url .= "&cpanel_jsonapi_func=$command";
		$url .= "&cpanel_jsonapi_apiversion=2";
		foreach ( $opts as $k => $v ) {
			$url .= "&{$k}=" . urlencode( $v );
		}

		return $url;
	}

	/**
	 * @param string $url
	 *
	 * @return array
	 * @throws Exception
	 */
	private function executeCallCpanel( $url ) {
		$res = $this->executeCall( $url );
		$res = json_decode( $res );

		if ( isset( $res->cpanelresult->error ) ) {
			throw new Exception( $res->cpanelresult->error );
		}

		return $res->cpanelresult->data;
	}

	private function executeCall( $url ) {
		$curl = curl_init();
		curl_setopt( $curl, CURLOPT_SSL_VERIFYHOST, 0 );
		curl_setopt( $curl, CURLOPT_SSL_VERIFYPEER, 0 );
		curl_setopt( $curl, CURLOPT_RETURNTRANSFER, 1 );

		if ( $this->loginType === 'auth_basic' ) {
			$header[0] = "Authorization: Basic " . base64_encode( $this->user . ":" . $this->pass );
		} else {
			$header[0] = "Authorization: whm {$this->user}:{$this->token}";
		}
		curl_setopt( $curl, CURLOPT_HTTPHEADER, $header );
		curl_setopt( $curl, CURLOPT_URL, $url );

		$result = curl_exec( $curl );

		$http_status = curl_getinfo( $curl, CURLINFO_HTTP_CODE );
		if ( $http_status != 200 ) {
			var_dump( $url, $http_status, $result );
		}

		return $result;
	}
}

function get_bin_php() {
	global $bin_php;

	if ( null === $bin_php ) {
		$possibilities = array(
			'/usr/local/bin/php',
			'/usr/bin/php'
		);

		foreach ( $possibilities as $possibility ) {
			if ( file_exists( $possibility ) ) {
				$bin_php = $possibility;
				break;
			}
		}

		if ( ! file_exists( $bin_php ) ) {
			$bin_php = trim( shell_exec( 'which php' ) );
		}
	}

	return $bin_php;
}

function get_bin_wp() {
	global $bin_wp;

	if ( null === $bin_wp ) {
		$possibilities = array(
			'/usr/local/bin/wp',
			'/usr/bin/wp',
			'~/wp-cli.phar'
		);

		foreach ( $possibilities as $possibility ) {
			if ( file_exists( $possibility ) ) {
				$bin_wp = $possibility;
				break;
			}
		}
	}

	return $bin_wp;
}

function wpcli_exec( $command, $path = null ) {
	global $bin_php, $bin_wp;

	$complete_command = "$bin_php $bin_wp $command";
	if ( $path ) {
		$complete_command .= " --path='$path'";
	}

	return trim( shell_exec( $complete_command ) );
}

/**
 * Pluck an array of values from an array. (Only for PHP 5.3+)
 *
 * @param  $array - data
 * @param  $key - value you want to pluck from array
 *
 * @return array only with key data
 *
 * @see https://gist.github.com/ozh/82a17c2be636a2b1c58b49f271954071#file-pluck-php
 */
function array_pluck( $array, $key ) {
	return array_map( function ( $v ) use ( $key ) {
		return is_object( $v ) ? $v->$key : $v[ $key ];
	}, $array );
}

/**
 * @param string $dir
 *
 * @return int
 *
 * @see https://gist.github.com/eusonlito/5099936
 */
function folderSize( $dir ) {
	$size = 0;

	foreach ( glob( rtrim( $dir, '/' ) . '/*', GLOB_NOSORT ) as $each ) {
		$size += is_file( $each ) ? filesize( $each ) : folderSize( $each );
	}

	return $size;
}

/**
 * Open browser native auth request popup
 */
function send_http_unauthorized() {
	header( 'HTTP/1.1 401 Authorization Required' );
	header( 'WWW-Authenticate: Basic realm="Access denied"' );
	exit;
}

/**
 * @param array $data
 * @param bool $success
 */
function send_json_reply( $data, $success = true ) {
	$output = array_merge( $data, array(
		'swim_client_version' => SWIM_CLIENT_VERSION,
		'success'             => $success,
	) );

	header( 'Content-Type: application/json' );

	$json_str = json_encode( $output, JSON_PRETTY_PRINT );
	if ( ! $json_str ) {
		$output   = mb_convert_encoding( $output, 'UTF-8' );
		$json_str = json_encode( $output, JSON_PRETTY_PRINT );
	}

	if ( ! $json_str ) {
		$json_str = json_encode( array(
			'swim_client_version' => SWIM_CLIENT_VERSION,
			'error'               => 'Cant json_encode the collected output.',
		), JSON_PRETTY_PRINT );
	}

	echo $json_str;
	if ( SWIM_DEBUG ) {
		var_dump( $output );
	}
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
