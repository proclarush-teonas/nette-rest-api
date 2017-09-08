<?php
/**
 * Created by PhpStorm.
 * User: Jim
 * Date: 5.9.2017
 * Time: 14:52
 */

namespace NetteRestApi;

use NetteRestApi\ApiValidateException;

class ApiRouteProcessor {



	//TODO - Karel - rozcupovat na mensi funkce, aby to bylo prehlednejsi

	const TYPE_FLOAT = 'float';
	const TYPE_INT = 'int';
	const TYPE_STRING = 'string';
	const TYPE_NULL = 'null';
	const TYPE_ARRAY = 'array';
	const TYPE_BOOL = 'bool';


	/** @var  string $path */
	protected $path;
	/** @var  array $routes */
	protected $routes;
	/** @var  \Nette\Http\IResponse $httpResponse */
	protected $httpResponse;

	/**
	 * expects routes from neon config
	 * @param array $routes
	 */
	public function __construct($routes = array()){
		$this->routes = $routes;
	}


	public function process(\Nette\Http\IRequest $httpRequest, \Nette\DI\Container $container, \Nette\Http\IResponse $httpResponse){
		$this->path = str_replace($httpRequest->getUrl()->getBasePath(), '', $httpRequest->getUrl()->getPath());
		$this->httpResponse = $httpResponse;

		try {
			if (array_key_exists($this->path, $this->routes)) {

				$conf = $this->routes[$this->path];
				$presenter = $conf['presenter'];
				$methods = $conf['method'];

				if (array_key_exists($httpRequest->getMethod(), $methods)) {
					$functionName = $methods[$httpRequest->getMethod()];


					$presenterService = $container->getByType($presenter);
					if (method_exists($presenterService, $functionName)) {

						$rm = new \ReflectionMethod($presenterService, $functionName);
						$params = $rm->getParameters();
						$comment = $rm->getDocComment();

						preg_match_all('~@param\s+([^\s]*)\s+([^\s]+)\n~i', $comment, $matches, PREG_PATTERN_ORDER);
						list($whole, $types, $names) = $matches;
						$paramsToCallInOrder = array();

						foreach ($params as $key => $rp) {
							if (!isset($_REQUEST[$rp->getName()])) {
								if($rp->isOptional()) {
									$paramValue = $rp->getDefaultValue();
								} else {
									throw new ApiValidateException('missing parameter ' . $rp->getName(), ApiValidateException::ERR_CODE_MISSING_PARAM);
								}
							} else {
								$paramValue = $_REQUEST[$rp->getName()];
							}

							$keys = array_keys($names, '$'.$rp->getName());

							if (count($keys) === 1) {
								$type = $types[array_shift($keys)];
								$validated = false;
								if (strpos($type, '|')) {
									$allowedTypes = explode('|', $type);
									foreach ($allowedTypes as $aType) {
										$aTr = $this->validateTypeVar($paramValue, $aType);
										if ($aTr) {
											$validated = true;
											break;
										}
									}
								} else {
									$validated = $this->validateTypeVar($paramValue, $type);
								}
								if (!$validated) {
									$msg = 'variable ' . $rp->getName() . ' (' . $type . ') is not a ' . $type . '. value: ' . $paramValue;
									throw new ApiValidateException($msg, ApiValidateException::ERR_CODE_WRONG_TYPE);
								}
							}
							$paramsToCallInOrder[$rp->getName()] = $paramValue;
						}
						$result = call_user_func_array(array($presenterService, $functionName), $paramsToCallInOrder);
						$status = 200;
					} else {
						throw new ApiValidateException('requested method ('.$functionName.') does not exist', ApiValidateException::ERR_CODE_URI_NOT_FOUND);
					}
				} else {
					throw new ApiValidateException('used method ('.$httpRequest->getMethod().') not allowed', ApiValidateException::ERR_CODE_WRONG_METHOD);
				}
			} else {
				throw new ApiValidateException('requested uri ('.$this->path.') does not exist', ApiValidateException::ERR_CODE_URI_NOT_FOUND);
			}
		} catch (\Exception $e){
			$result = $e->getMessage();
			$status = $e->getCode();
		}
		$this->respond($result, $status);
	}

	/**
	 * @param mixed $message
	 * @param int $code
	 * @param string $contentType
	 * @param string $encoding
	 * @throws \Nette\Application\AbortException => aborts application executing
	 */
	protected function respond($message, $code = 200, $contentType = 'application/json', $encoding = 'UTF-8'){
		//kdyz to nespecifikujou u exception, kterou si vyhodej sami
		if($code < 200) { $code = 500; }

		$response = $this->httpResponse;
		$response->setContentType($contentType, $encoding);
		$response->setCode($code);
		echo json_encode($message);
		throw new \Nette\Application\AbortException;
	}



	/**
	 * nevaliduje objekty!
	 * @param mixed $value
	 * @param string $type
	 * @return bool
	 */
	protected function validateTypeVar(&$value, $type){
		if($type == static::TYPE_FLOAT){
			$value = filter_var($value, FILTER_VALIDATE_FLOAT);
			if(!is_float($value)){
				return false;
			}
		}
		if($type == static::TYPE_STRING && !is_string($value)){
			return false;
		}
		if($type == static::TYPE_INT){
			$value = filter_var($value, FILTER_VALIDATE_INT);
			if(!is_int($value)){
				return false;
			}
		}
		if($type == static::TYPE_NULL){
			$value = $value ? : null;
			if(!is_null($value)){
				return false;
			}
		}
		if($type == static::TYPE_ARRAY && !is_array($value)){
			return false;
		}
		if($type == static::TYPE_BOOL){
			$value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
			if(!is_bool($value)){
				return false;
			}
		}
		return true;
	}

}