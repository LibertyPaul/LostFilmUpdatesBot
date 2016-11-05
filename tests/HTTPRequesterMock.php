<?php

require_once(__DIR__.'/../HTTPRequesterInterface.php');

class HTTPRequesterMock implements HTTPRequesterInterface{
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
	
	public function sendJSONRequest($destination, $content_json){
		$res = file_put_contents($this->destinationFilePath, $content_json);
		if($res === false){
			throw new Exception('HTTPRequesterMock::sendJSONRequest file_put_contents error');
		}
		
		return $this->successResponse();
	}
}		
