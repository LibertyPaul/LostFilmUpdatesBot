<?php

require_once(__DIR__.'/HTTPRequesterInterface.php');

class HTTPRequester implements HTTPRequesterInterface{
	private $curl;

	public function __construct(){
		$this->curl = curl_init();
		assert($this->curl !== false);
		assert(curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true));
	}

	public function __destruct(){
		curl_close($this->curl);
	}

	public function sendJSONRequest($destination, $content_json){
		assert(curl_setopt($this->curl, CURLOPT_URL, $destination));
		assert(curl_setopt($this->curl, CURLOPT_POST, true));
		assert(curl_setopt($this->curl, CURLOPT_POSTFIELDS, $content_json));
		assert(curl_setopt($this->curl, CURLOPT_HTTPHEADER, array('Content-type: application/json')));

		$response = curl_exec($this->curl);
		if($response === false){
			throw new HTTPException('curl_exec error: '.curl_error($this->curl));
		}

		return array(
			'value' => $response,
			'code' => intval(curl_getinfo($this->curl, CURLINFO_HTTP_CODE))
		);

	}
	
	public function sendGETRequest($destination){
		assert(curl_setopt($this->curl, CURLOPT_URL, $destination));
		assert(curl_setopt($this->curl, CURLOPT_HTTPGET, true));
		assert(curl_setopt($this->curl, CURLOPT_HTTPHEADER, array('Content-type: text/html')));
		
		$response = curl_exec($this->curl);
		if($response === false){
			throw new HTTPException('curl_exec error: '.curl_error($this->curl));
		}

		return array(
			'value' => $response,
			'code' => intval(curl_getinfo($this->curl, CURLINFO_HTTP_CODE))
		);
	}
		
}
