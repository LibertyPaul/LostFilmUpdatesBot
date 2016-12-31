<?php

require_once(__DIR__.'/HTTPRequesterInterface.php');

class HTTPRequester implements HTTPRequesterInterface{
	private $curl;

	public function __construct(){
		$this->curl = curl_init();
		assert($this->curl !== false);
		assert(
			curl_setopt_array(
				$this->curl,
				array(
					CURLOPT_RETURNTRANSFER 	=> true,
					CURLOPT_POST			=> true,
					CURLOPT_HTTPHEADER		=> array(
						'Content-type: application/json'
					)
				)
			)
		);
	}

	public function __destruct(){
		assert(curl_close($this->curl));
	}

	public function sendJSONRequest($destination, $content_json){
		assert(curl_setopt($this->curl, CURLOPT_URL, $destination));
		assert(curl_setopt($this->curl, CURLOPT_POSTFIELDS, $content_json));

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
