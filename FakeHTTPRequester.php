<?php

require_once(__DIR__.'/HTTPRequesterInterface.php');

class FakeHTTPRequester implements HTTPRequesterInterface{
	protected $destinationFilePath;
	
	public function __construct($destinationFilePath){
		$this->destinationFilePath = $destinationFilePath;
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
	
	public function sendJSONRequest($destination, $content_json){
		$res = file_put_contents($this->destinationFilePath, "\n\n$content_json", FILE_APPEND);
		if($res === false){
			throw new Exception('FakeHTTPRequester::sendJSONRequest file_put_contents error');
		}
		
		return $this->randomResponse();
	}

	public function sendGETRequest($destination){
		$res = file_put_contents($this->destinationFilePath, "\n\n$destination", FILE_APPEND);
		if($res === false){
			throw new Exception('FakeHTTPRequester::sendJSONRequest file_put_contents error');
		}
		
		return $this->randomResponse();
	}

}		
