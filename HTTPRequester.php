<?php

require_once(__DIR__.'/HTTPRequesterInterface.php');
require_once(__DIR__.'/Tracer.php');

class HTTPRequester implements HTTPRequesterInterface{
	private $curl;
	private $tracer;

	public function __construct(){
		$this->tracer = new Tracer(__CLASS__);
	
		$this->curl = curl_init();
		assert($this->curl !== false);
		assert(curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true));
		assert(curl_setopt($this->curl, CURLOPT_FOLLOWLOCATION, true));
		assert(curl_setopt($this->curl, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS));
	}

	public function __destruct(){
		curl_close($this->curl);
	}

	public function sendJSONRequest($destination, $content_json){
		assert(curl_setopt($this->curl, CURLOPT_URL, $destination));
		assert(curl_setopt($this->curl, CURLOPT_POST, true));
		assert(curl_setopt($this->curl, CURLOPT_POSTFIELDS, $content_json));
		assert(curl_setopt($this->curl, CURLOPT_HTTPHEADER, array('Content-type: application/json')));
		
		$this->tracer->logEvent('[JSON REQUEST]', __FILE__, __LINE__, $destination);
		$this->tracer->logEvent('[JSON REQUEST]', __FILE__, __LINE__, PHP_EOL.print_r($content_json, true));

		$response = curl_exec($this->curl);
		if($response === false){
			$this->tracer->logError('[HTTP ERROR]', __FILE__, __LINE__, 'curl_exec error: '.curl_error($this->curl));
			$this->tracer->logError('[HTTP ERROR]', __FILE__, __LINE__, "url: '$destination'");
			$this->tracer->logError('[HTTP ERROR]', __FILE__, __LINE__, PHP_EOL.$content_json);
			throw new HTTPException('curl_exec error: '.curl_error($this->curl));
		}

		$code = intval(curl_getinfo($this->curl, CURLINFO_HTTP_CODE));
		
		$this->tracer->logEvent('[JSON REQUEST]', __FILE__, __LINE__, 'HTTP code: '.$code);
		$this->tracer->logEvent('[JSON REQUEST]', __FILE__, __LINE__, 'Body:'.PHP_EOL.$response.PHP_EOL);

		return array(
			'value' => $response,
			'code' => $code
		);

	}
	
	public function sendGETRequest($destination){
		assert(curl_setopt($this->curl, CURLOPT_URL, $destination));
		assert(curl_setopt($this->curl, CURLOPT_HTTPGET, true));
		assert(curl_setopt($this->curl, CURLOPT_HTTPHEADER, array('Content-type: text/html')));
		
		$this->tracer->logEvent('[GET REQUEST]', __FILE__, __LINE__, $destination);
		
		$response = curl_exec($this->curl);
		if($response === false){
			$this->tracer->logError('[HTTP ERROR]', __FILE__, __LINE__, 'curl_exec error: '.curl_error($this->curl));
			throw new HTTPException('curl_exec error: '.curl_error($this->curl));
		}

		$code = intval(curl_getinfo($this->curl, CURLINFO_HTTP_CODE));
		
		$this->tracer->logEvent('[GET REQUEST]', __FILE__, __LINE__, 'HTTP code: '.$code);
		$this->tracer->logEvent('[GET REQUEST]', __FILE__, __LINE__, 'Body:'.PHP_EOL.$response.PHP_EOL);

		return array(
			'value' => $response,
			'code' => intval(curl_getinfo($this->curl, CURLINFO_HTTP_CODE))
		);
	}
		
}
