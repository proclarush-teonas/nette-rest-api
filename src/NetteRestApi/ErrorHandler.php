<?php
/**
 * Created by PhpStorm.
 * User: Jim
 * Date: 26.4.2017
 * Time: 13:48
 */

namespace NetteRestApi;


class ErrorHandler {

	public function __construct(){
		set_error_handler(array($this, 'error'));
		register_shutdown_function(array($this, 'shutdown'));
	}

	public function shutdown() {

		$err = error_get_last();

		if($err && isset($err['type'])) {
			switch ($err['type']) {
				case E_ERROR:
				case E_CORE_ERROR:
				case E_COMPILE_ERROR:
				case E_USER_ERROR:
				case E_RECOVERABLE_ERROR:
				case E_CORE_WARNING:
				case E_COMPILE_WARNING:
				case E_PARSE:
					$result = array('code'=>500, 'message'=>$err['message']);
					ob_end_clean();
					echo json_encode($result);
			}

		}
	}

	public function error($severity, $message, $filename, $lineno){
		if (error_reporting() & $severity) {
			$newMess = $message.' in file: '.$filename.' on line: '.$lineno;
			throw new \ErrorException($newMess, 500, $severity, $filename, $lineno);
		}
	}

}