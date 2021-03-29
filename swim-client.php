<?php
header( 'Cache-Control: no-cache, must-revalidate, max-age=0' );

define( 'SWIM_CLIENT_VERSION', '1.0.0' );
define( 'SWIM_CLIENT_DIR', __DIR__ );

// check request auth
// @see https://gist.github.com/rchrd2/c94eb4701da57ce9a0ad4d2b00794131
if ( empty( $_SERVER['PHP_AUTH_USER'] ) || empty( $_SERVER['PHP_AUTH_PW'] ) ) {
	send_http_unauthorized();
}

// cpanel instance
$host = isset( $_REQUEST['HTTP_HOST'] ) ? $_REQUEST['HTTP_HOST'] : gethostname(); // 127.0.0.1 ?
$port = 2083; // cPanel only

$cpanel = new Cpanel( $host, $port );
$cpanel->loginBasic( $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'] );
try {
	$addondomains = $cpanel->AddonDomain_listaddondomains();
} catch ( Exception $e ) {
	send_http_unauthorized();
}

// parse request
$action = isset( $_REQUEST['action'] ) ? $_REQUEST['action'] : '';
switch ( $action ) {
	case 'databases':
		$databases = $cpanel->MysqlFE_listdbs( get_current_user() );
		$db_users  = $cpanel->MysqlFE_listusers( get_current_user() );

		var_dump( $addondomains, $databases, $db_users );

		// now search for known frameworks
		// in public_html and addon domains folders
		// and complete database credentials as possible
		break;

	default:
		$data = array();
}


function send_http_unauthorized() {
	header( 'HTTP/1.1 401 Authorization Required' );
	header( 'WWW-Authenticate: Basic realm="Access denied"' );
	exit;
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
	 * @since 2.5
	 *
	 */
	public function MysqlFE_listdbs( $account ) {
		$url = $this->buildCpanelUrlV2( $account, 'MysqlFE', 'listdbs' );
		$res = $this->executeCallCpanel( $url );

		return $res;
	}

	/**
	 * @param string $account
	 *
	 * @return array
	 * @throws Exception
	 * @since 2.5
	 *
	 */
	public function MysqlFE_listusers( $account ) {
		$url = $this->buildCpanelUrlV2( $account, 'MysqlFE', 'listusers' );
		$res = $this->executeCallCpanel( $url );

		return $res;
	}

	private function buildCpanelUrlV2( $user, $module, $command, $opts = [] ) {
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

	private function buildWhmUrlV1( $command, $opts = [] ) {
		$url = "https://{$this->host}:{$this->port}/json-api/{$command}?api.version=1";
		foreach ( $opts as $k => $v ) {
			$url .= "&{$k}={$v}";
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

	/**
	 * @param string $url
	 *
	 * @return array
	 * @throws Exception
	 */
	private function executeCallWhm( $url ) {
		$res = $this->executeCall( $url );
		$res = json_decode( $res );

		if ( ! $res->metadata->result ) {
			throw new Exception( $res->metadata->reason );
		}

		return $res->data;
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
