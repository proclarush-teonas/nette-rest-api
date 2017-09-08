<?php
/**
 * Created by PhpStorm.
 * User: Jim
 * Date: 5.9.2017
 * Time: 10:47
 */
namespace NetteRestApi\Exception;

class ApiValidateException extends \Exception {

	const ERR_CODE_WRONG_METHOD = 405;
	const ERR_CODE_WRONG_TYPE = 400;
	const ERR_CODE_MISSING_PARAM = 400;
	const ERR_CODE_URI_NOT_FOUND = 404;


}