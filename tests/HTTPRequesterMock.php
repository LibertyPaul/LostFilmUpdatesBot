<?php

require_once(__DIR__.'/../HTTPRequesterInterface.php');

class HTTPRequesterMock implements HTTPRequesterInterface{
	protected $destinationFilePath;
	
	public function __construct($destinationFilePath){
		$this->destinationFilePath = $destinationFilePath;
	}
	
	private function successResponse(){
		$resp = array(
			'ok' => true,
			'result' => null
		);
		
		return json_encode($resp);
	}
	
	public function sendJSONRequest($destination, $content_json){
		$output = array(
			'destination' 	=> $destination,
			'content_json'	=> json_decode($content_json, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK)
		);
		
		$output_json = json_encode($output, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK).PHP_EOL.PHP_EOL;
		
		$res = file_put_contents($this->destinationFilePath, $output_json, FILE_APPEND);
		if($res === false){
			throw new Exception('HTTPRequesterMock::sendJSONRequest file_put_contents error');
		}
		
		return $this->successResponse();
	}
}		
