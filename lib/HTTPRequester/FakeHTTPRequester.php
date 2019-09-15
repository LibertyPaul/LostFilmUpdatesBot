<?php

namespace HTTPRequester;

require_once(__DIR__.'/HTTPRequesterInterface.php');
require_once(__DIR__.'/HTTPResponse.php');
require_once(__DIR__.'/../Tracer/Tracer.php');

class FakeHTTPRequester implements HTTPRequesterInterface{
	private $destinationFilePath;
	private $tracer;
	
	public function __construct($destinationFilePath){
		$this->destinationFilePath = $destinationFilePath;
		$this->tracer = new \Tracer(__CLASS__);
	}
	
	private function successResponse(){
		$telegram_resp = array(
			'ok' => true,
			'result' => array(
				'message_id' => 42424242
			)
		);

		$resp = new HTTPResponse(200, json_encode($telegram_resp));
		return $resp; 
	}
	
	private function failureResponse(){
		$telegram_resp = array(
			'ok' => false
		);
		
		$resp = new HTTPResponse(403, json_encode($telegram_resp));
		return $resp;
	}
	
	private function randomResponse(){
		if(rand(0, 100) > 50){
			return $this->successResponse();
		}
		else{
			return $this->failureResponse();
		}
	}

	private function writeOut($text){
		$this->tracer->logDebug('[o]', __FILE__, __LINE__, $text);
	}
	
	public function request(HTTPRequestProperties $requestProperties){
		$this->writeOut($requestProperties);
		return $this->randomResponse();
	}

	public function multiRequest(array $requestsProperties){
		foreach($requestsProperties as $request){
			$this->request($request);
		}
	}
}		
