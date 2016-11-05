<?php

require_once(__DIR__.'/HTTPRequesterInterface.php');

class HTTPRequester implements HTTPRequesterInterface{
	
	protected function getHTTPCode($headers){
		$matches = array();
		$res = preg_match_all('/[\w]+\/\d\.\d (\d+) [\w]+/', $headers[0], $matches);
		
		$code = intval($matches[1][0]);
		return $code;
	}

	public function sendJSONRequest($destination, $content_json){
		$context = stream_context_create(
			array(
				'http' => array(
					'method' => 'POST',
					'header' => 'Content-type: application/json',
					'content' => $content_json
				)
			)
		);
		//TODO: move to cURL in order to get meaningful messages along with 4xx codes
		$response = file_get_contents($destination, false, $context);
	
		$respCode = $this->getHTTPCode($http_response_header);
		if($response === false){
			throw new HTTPException("file_get_contents fail", $respCode);
		}

		return array(
			'value' => $response,
			'code'	=> $respCode	
		);
	}
}
