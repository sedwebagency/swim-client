<?php
define( 'SWIM_CLIENT_VERSION', '1.0.0' );
define( 'SWIM_CLIENT_PATH', __DIR__ );

$data = array(
	'swim_client_version' => SWIM_CLIENT_VERSION
);

header( 'Content-Type: application/json' );
echo json_encode( $data, JSON_PRETTY_PRINT );
exit;
