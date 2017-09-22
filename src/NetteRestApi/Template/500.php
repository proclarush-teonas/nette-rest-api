<?php
/**
 * Created by PhpStorm.
 * User: Jim
 * Date: 22.9.2017
 * Time: 14:00
 */

header('Content-Type:application/json', true, 500);
$err = array(
	'errors' => array(
		'status' => "500",
		'title' => "Internal Server Error",
		'detail' => "Service is unavailable",
		'code' => "500",
	),
);
echo json_encode($err);
exit();