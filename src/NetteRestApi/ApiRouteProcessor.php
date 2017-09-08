<?php
/**
 * Created by PhpStorm.
 * User: Jim
 * Date: 5.9.2017
 * Time: 14:52
 */

namespace NetteRestApi;

use NetteRestApi\Exception\ApiValidateException;

class ApiRouteProcessor {

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
	/** @var  \Nette\Http\IRequest $httpRequest */
	protected $httpRequest;

	/**
	 * expects routes from neon config
	 * @param array $routes
	 */
	public function __construct($routes = array()){
		$this->routes = $routes;
	}

	/**
	 * @param \Nette\Http\IRequest $httpRequest
	 * @param \Nette\DI\Container $container
	 * @param \Nette\Http\IResponse $httpResponse
	 * @throws \Nette\Application\AbortException
	 */
	public function process(\Nette\Http\IRequest $httpRequest, \Nette\DI\Container $container, \Nette\Http\IResponse $httpResponse){
		$this->httpRequest = $httpRequest;
		$this->httpResponse = $httpResponse;
		//to allow override
		if(!$this->path) {
			$this->setPath();
		}

		try {
			if (!array_key_exists($this->path, $this->routes)) {
				throw new ApiValidateException('requested uri ('.$this->path.') does not exist', ApiValidateException::ERR_CODE_URI_NOT_FOUND);
			}

			list($presenter, $functionName) = $this->getRequestedPresenterAndMethodName();

			$presenterService = $container->getByType($presenter);

			/** @var \ReflectionParameter[] $params */
			list($params, $comment) = $this->getMethodParamsAndDocComment($presenterService, $functionName);

			$paramsToCallInOrder = $this->assembleAndValidateParams($params, $comment);

			$result = call_user_func_array(array($presenterService, $functionName), $paramsToCallInOrder);
			$status = 200;
		} catch (\Exception $e) {
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
		if ($code < 200) { $code = 500; }

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
	protected function validateTypeVar($value, $type){
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

	/**
	 * @param string|null $path
	 */
	protected function setPath($path = null){
		if($path){
			$this->path = $path;
		} else {
			$this->path = str_replace($this->httpRequest->getUrl()->getBasePath(), '', $this->httpRequest->getUrl()->getPath());
		}
	}

	/**
	 * @return array (presenterName, functionName)
	 * @throws ApiValidateException
	 */
	protected function getRequestedPresenterAndMethodName(){
		$conf = $this->routes[$this->path];
		$presenter = $conf['presenter'];
		$methods = $conf['method'];

		if (array_key_exists($this->httpRequest->getMethod(), $methods)) {
			$functionName = $methods[$this->httpRequest->getMethod()];
		} else {
			throw new ApiValidateException('used method ('.$this->httpRequest->getMethod().') not allowed', ApiValidateException::ERR_CODE_WRONG_METHOD);
		}
		return array($presenter, $functionName);
	}

	/**
	 * @param object $presenterService
	 * @param string $functionName
	 * @return array (ReflectionParameter[], string)
	 * @throws ApiValidateException
	 */
	protected function getMethodParamsAndDocComment($presenterService, $functionName){
		if (method_exists($presenterService, $functionName)) {
			$rm = new \ReflectionMethod($presenterService, $functionName);
			$params = $rm->getParameters();
			$comment = $rm->getDocComment();
		} else {
			throw new ApiValidateException('requested method ('.$functionName.') does not exist', ApiValidateException::ERR_CODE_URI_NOT_FOUND);
		}
		return array($params, $comment);
	}

	/**
	 * @param \ReflectionParameter[] $params
	 * @param string $comment
	 * @return array
	 * @throws ApiValidateException
	 */
	protected function assembleAndValidateParams($params, $comment){
		list($whole, $types, $names) = $this->parseCommentBlock($comment);
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
				$this->validateByComment($types, $paramValue, $rp->getName());
			}
			$paramsToCallInOrder[$rp->getName()] = $paramValue;
		}
		return $paramsToCallInOrder;
	}

	/**
	 * @param array $types
	 * @param mixed $paramValue
	 * @param string $paramName
	 * @throws ApiValidateException
	 */
	protected function validateByComment($types, $paramValue, $paramName){
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
			$msg = 'variable ' . $paramName . ' (' . $type . ') is not a ' . $type . '. value: ' . $paramValue;
			throw new ApiValidateException($msg, ApiValidateException::ERR_CODE_WRONG_TYPE);
		}
	}

	/**
	 * @param string $comment
	 * @return array
	 */
	protected function parseCommentBlock($comment){
		preg_match_all('~@param\s+([^\s]*)\s+([^\s]+)\n~i', $comment, $matches, PREG_PATTERN_ORDER);
		return $matches;
	}
}