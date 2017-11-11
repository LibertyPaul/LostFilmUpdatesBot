<?php

namespace HTTPRequester;

require_once(__DIR__.'/HTTPRequesterInterface.php');
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

		$resp = array(
			'value' => json_encode($telegram_resp),
			'code' => 200
		);
		
		return $resp; 
	}
	
	private function failureResponse(){
		$telegram_resp = array(
			'ok' => false
		);
		
		$resp = array(
			'value' => json_encode($telegram_resp),
			'code' => 403
		);
		
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
	
	public function sendJSONRequest($destination, $content_json){
		$this->tracer->logEvent('[JSON REQUEST]', __FILE__, __LINE__, $destination);
		$this->tracer->logEvent(
			'[JSON REQUEST]', __FILE__, __LINE__,
			PHP_EOL.print_r($content_json, true)
		);

		$this->writeOut($content_json);	
		return $this->randomResponse();
	}

	public function sendGETRequest($destination, $payload = null){
		assert(is_string($destination));

		$request = $destination;
		if($payload !== null){
			assert(strpos($destination, '?') === false);
			if(is_array($payload)){
				$request .= '?'.http_build_query($payload);
			}
			elseif(is_string($payload)){
				$request .= '?'.$payload;
			}
			else{
				throw new \LogicException('Incorrect payload type: '.gettype($payload));
			}
		}

		$this->tracer->logEvent('[GET REQUEST]', __FILE__, __LINE__, $request);
		$this->writeOut($request);	
		return $this->randomResponse();
	}

	public function sendPOSTRequest($URL, $payload = null){}

	public function request(HTTPRequestProperties $requestProperties){}
	public function multiRequest(array $requestsProperties){}
}		
