<?php

require_once(__DIR__.'/HTTPRequesterInterface.php');
require_once(__DIR__.'/../Tracer/Tracer.php');

class HTTPRequester implements HTTPRequesterInterface{
	private $curl;
	private $tracer;

	public function __construct(){
		$this->tracer = new \Tracer(__CLASS__);
	
		$this->curl = curl_init();
		assert($this->curl !== false);
		assert(curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true));
		assert(curl_setopt($this->curl, CURLOPT_FOLLOWLOCATION, true));
		assert(curl_setopt($this->curl, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS));
	}

	public function __destruct(){
		curl_close($this->curl);
	}

	private static function prettifyIfPossible($JSON){
		$obj = json_decode($JSON);
		if($obj === false){
			return $JSON;
		}

		return json_encode($obj, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
	}

	public function sendJSONRequest($destination, $content_json){
		assert(curl_setopt($this->curl, CURLOPT_URL, $destination));
		assert(curl_setopt($this->curl, CURLOPT_POST, true));
		assert(curl_setopt($this->curl, CURLOPT_POSTFIELDS, $content_json));
		assert(
			curl_setopt(
				$this->curl,
				CURLOPT_HTTPHEADER,
				array('Content-type: application/json')
			)
		);
		
		$this->tracer->logEvent(
			'[JSON REQUEST]', __FILE__, __LINE__,
			'Destination: '.$destination.PHP_EOL.
			'Request: '.PHP_EOL.$content_json
		);

		$response = curl_exec($this->curl);
		if($response === false){
			$this->tracer->logError(
				'[HTTP ERROR]', __FILE__, __LINE__, 
				'curl_exec error: '.curl_error($this->curl).PHP_EOL.
				"url: '$destination'".PHP_EOL.
				$content_json
			);
			
			throw new HTTPException('curl_exec error: '.curl_error($this->curl));
		}

		$code = intval(curl_getinfo($this->curl, CURLINFO_HTTP_CODE));
		
		$this->tracer->logEvent(
			'[JSON REQUEST]', __FILE__, __LINE__,
			'HTTP code: '.$code.PHP_EOL.
			'Response: '.PHP_EOL.
			self::prettifyIfPossible($response)
		);

		return array(
			'value' => $response,
			'code' => $code
		);

	}
	
	public function sendGETRequest($destination, array $args = null){
		assert(is_string($destination));

		$request = $destination;
		if($args !== null){
			assert(strpos($destination, '?') === false);
			$request .= '?'.http_build_query($args);
		}

		assert(curl_setopt($this->curl, CURLOPT_URL, $request));
		assert(curl_setopt($this->curl, CURLOPT_HTTPGET, true));
		assert(curl_setopt($this->curl, CURLOPT_HTTPHEADER, array('Content-type: text/html')));
		
		$this->tracer->logEvent(
			'[GET REQUEST]', __FILE__, __LINE__,
			"Destination=[$destination], args:".PHP_EOL.
			print_r($args, true)	
		);
		
		$response = curl_exec($this->curl);
		if($response === false){
			$this->tracer->logError(
				'[HTTP ERROR]', __FILE__, __LINE__,
				'curl_exec error: '.curl_error($this->curl)
			);

			throw new HTTPException('curl_exec error: '.curl_error($this->curl));
		}

		$code = intval(curl_getinfo($this->curl, CURLINFO_HTTP_CODE));
		
		$this->tracer->logEvent(
			'[GET RESPONSE]', __FILE__, __LINE__,
			"HTTP code: [$code]".PHP_EOL.
			'Body:'.PHP_EOL.
			$response
		);

		return array(
			'value' => $response,
			'code' => intval(curl_getinfo($this->curl, CURLINFO_HTTP_CODE))
		);
	}
		
}
