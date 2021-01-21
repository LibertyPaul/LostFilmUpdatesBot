<?php

namespace HTTPRequester;

require_once(__DIR__.'/HTTPRequesterInterface.php');
require_once(__DIR__.'/HTTPResponse.php');
require_once(__DIR__.'/../Tracer/TracerFactory.php');

class FakeHTTPRequester implements HTTPRequesterInterface{
	private $destinationFilePath;
	private $tracer;
	
	public function __construct($destinationFilePath){
		$this->destinationFilePath = $destinationFilePath;
		$this->tracer = \TracerFactory::getTracer(__CLASS__, null, true, false);
	}
	
	private function successResponse(): HTTPResponse {
		$telegram_resp = array(
			'ok' => true,
			'result' => array(
				'message_id' => 42424242
			)
		);

		$resp = new HTTPResponse(200, json_encode($telegram_resp));
		return $resp; 
	}
	
	private function failureResponse(): HTTPResponse {
		$telegram_resp = array(
			'ok' => false
		);
		
		$resp = new HTTPResponse(403, json_encode($telegram_resp));
		return $resp;
	}
	
	private function randomResponse(): HTTPResponse {
		if(rand(0, 100) > 50){
			return $this->successResponse();
		}
		else{
			return $this->failureResponse();
		}
	}

	private function writeOut($text): void {
		$this->tracer->logDebug('[o]', __FILE__, __LINE__, $text);
	}

	private static function isTelegramFilePropertiesRequest(HTTPRequestProperties $requestProperties): bool {
		$URL = $requestProperties->getURL();
		$payload = $requestProperties->getPayload();

		return
			is_array($payload) && array_key_exists('file_id', $payload)
			&&
			strpos($URL, 'https://api.telegram.org/bot') === 0
			&&
			strpos($URL, '/getFile') > 0;
	}

	private static function isTelegramFileDownloadRequest(HTTPRequestProperties $requestProperties): bool {
		$URL = $requestProperties->getURL();

		return
			strpos($URL, 'https://api.telegram.org/file/bot') === 0;
	}

	private static function isGoogleSpeechAPIRequest(HTTPRequestProperties $requestProperties): bool {
		$URL = $requestProperties->getURL();
		
		return
			strpos($URL, 'https://speech.googleapis.com/v1/speech:recognize?key=') === 0;
	}


	private function createTelegramFilePropertiesResponse(): HTTPResponse {
		$telegram_resp = array(
			'ok' => true,
			'result' => array(
				'file_path' => 'dummy_file_path'
			)
		);

		$resp = new HTTPResponse(200, json_encode($telegram_resp));
		return $resp;		
	}

	private function createTelegramFileDownloadResponse(): HTTPResponse {
		$resp = new HTTPResponse(200, "Dummy response body");
		return $resp;
	}

	private function createGoogleSpeechAPIResponse(): HTTPResponse {
		$google_resp = array(
			'results' => array(
				array(
					'alternatives' => array(
						array('transcript' => 'option1', 'confidence' => 30),
						array('transcript' => 'option2', 'confidence' => 20),
						array('transcript' => 'option3', 'confidence' => 10),
					)
				)
			)
		);

		$resp = new HTTPResponse(200, json_encode($google_resp));
		return $resp;		
	}
	
	public function request(HTTPRequestProperties $requestProperties): HTTPResponse {
		$this->writeOut($requestProperties);

		if(self::isTelegramFilePropertiesRequest($requestProperties)){
			return $this->createTelegramFilePropertiesResponse();
		}
		elseif(self::isTelegramFileDownloadRequest($requestProperties)){
			return $this->createTelegramFileDownloadResponse();
		}
		elseif(self::isGoogleSpeechAPIRequest($requestProperties)){
			return $this->createGoogleSpeechAPIResponse();
		}
		else{
			return $this->randomResponse();
		}
	}

	public function multiRequest(array $requestsProperties): array {
		$res = array();

		foreach($requestsProperties as $request){
			$res[] = $this->request($request);
		}

		return $res;
	}
}		
