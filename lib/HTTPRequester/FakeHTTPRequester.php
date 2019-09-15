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
			'ok' => true
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
		$dir = dirname($this->destinationFilePath);
		if(file_exists($dir)){
			if(is_dir($dir) === false){
				syslog(LOG_CRIT, "[FAKE HTTP Rq] logs dir is not a directory ($dir)");
				throw new \Exception("Unable to open $dir directory");
			}
			$exists = file_exists($this->destinationFilePath);
		}
		else{
			assert(mkdir($dir, 0777, true));
			$exists = false;
		}

		if($exists === false){
			assert(touch($this->destinationFilePath));
			assert(chmod($this->destinationFilePath, 0666));
		}
			
		$res = file_put_contents($this->destinationFilePath, PHP_EOL.PHP_EOL.$text, FILE_APPEND);
		if($res === false){
			throw new \Exception('FakeHTTPRequester::sendJSONRequest file_put_contents error');
		}
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
