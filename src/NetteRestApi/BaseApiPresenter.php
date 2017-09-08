<?php
/**
 * Created by PhpStorm.
 * User: Jim
 * Date: 6.9.2017
 * Time: 13:51
 */

namespace NetteRestApi;


use Nette\Application\UI\Presenter;
use NetteRestApi\Exception\ApiErrorException;

class BaseApiPresenter extends Presenter {

	/** @var  ApiRouteProcessor $routeService @inject */
	public $routeService;

	/**
	 * place this method preferably into some baseApiPresenter or use this presenter
	 */
	public function startup(){
		//your router has to direct request into api part of your application,
		//where presenters extends some baseApiPresenter with this method,
		try {
			$this->routeService->process($this->getHttpRequest(), $this->getContext(), $this->getHttpResponse());
		} catch (\Exception $e) {
			if($e instanceof \Nette\Application\AbortException){
				throw $e;
			}
			throw new ApiErrorException($e->getMessage(), ApiErrorException::ERR_CODE_INFERNAL, $e);
		}

		//if this happens, something is very wrong, should be terminated at the end of process method
		parent::startup();
	}






	///// some example methods by example/config/routes.neon
	// its imperative to have annotation in comment above method in order to validate type of params

	/**
	 * @param int $id
	 * @param string|null $something
	 * @return array
	 */
//	public function getMark($id, $something = null){
//		return array(
//			'id'=>$id,
//			'some-mark-data' => array(1,2,3,4),
//		);
//	}

	/**
	 * @param int $id
	 * @param array $someMarkData
	 * @param $something
	 * @return bool
	 */
//	public function postMark($id, array $someMarkData, $something){
//		//type of $something param will not be validated
//		return true;
//	}

}