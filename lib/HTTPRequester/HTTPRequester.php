<?php

namespace HTTPRequester;

require_once(__DIR__.'/HTTPResponse.php');
require_once(__DIR__.'/CURLPool.php');
require_once(__DIR__.'/HTTPRequesterInterface.php');
require_once(__DIR__.'/HTTPRequestProperties.php');
require_once(__DIR__.'/../Tracer/TracerFactory.php');

class HTTPRequester implements HTTPRequesterInterface{
	private $curlPool;
	private $curl_multi = null;
	private $requestResponseTracer;

	public function __construct(){
		$this->requestResponseTracer = \TracerFactory::getTracer(__CLASS__, null, true, false);
		$this->curlPool = new CURLPool();
	}

	public function __destruct(){
		if($this->curl_multi !== null){
			curl_multi_close($this->curl_multi);
		}
	}

	private static function initializeCurl($curl){
		assert(curl_setopt($curl, \CURLOPT_RETURNTRANSFER, true));
		assert(curl_setopt($curl, \CURLOPT_FOLLOWLOCATION, true));
		assert(curl_setopt($curl, \CURLOPT_PROTOCOLS, \CURLPROTO_HTTP | \CURLPROTO_HTTPS));
		assert(curl_setopt($curl, \CURLOPT_CONNECTTIMEOUT, 10));
		assert(curl_setopt($curl, \CURLOPT_TIMEOUT, 10));
	}

	private static function createMultiCurl(){
		$multiCurl = curl_multi_init();
		if($multiCurl === false) {
		    throw new \RuntimeException("Failed to initialize multi cURL handle.");
		}

		return $multiCurl;
	}

	private function getCurl(){
		$this->curlPool->reserve(1);
		self::initializeCurl($this->curlPool->getFirst());

		return $this->curlPool->getFirst();
	}

	private function getCurlMulti(){
		if($this->curl_multi === null){
			$this->curl_multi = self::createMultiCurl();
		}

		return $this->curl_multi;
	}

	private function executeCurl($curl): HTTPResponse {
		$body = curl_exec($curl);
		if($body === false){
			$errno = curl_errno($curl);
			switch($errno){
				case \CURLE_OPERATION_TIMEOUTED:
					throw new HTTPTimeoutException(curl_error($curl), $errno);

				default:
					throw new HTTPException('curl_exec error: '.curl_error($curl), $errno);
			}
		}

		$code = intval(curl_getinfo($curl, \CURLINFO_HTTP_CODE));

		return new HTTPResponse($code, $body);
	}

	private function createGetRequest(string $URL, $payload = null): string {
		$request = $URL;

		if(empty($payload) === false){
			assert(strpos($request, '?') === false);
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

		return $request;
	}

	private function setRequestOptions($curl, HTTPRequestProperties $requestProperties): void {
		switch($requestProperties->getRequestType()){
			case RequestType::Get:
				assert(curl_setopt($curl, \CURLOPT_HTTPGET, true));
				$URL = $this->createGetRequest(
					$requestProperties->getURL(),
					$requestProperties->getPayload()
				);

				assert(curl_setopt($curl, \CURLOPT_URL, $URL));
				break;

			case RequestType::Post:
				assert(curl_setopt($curl, \CURLOPT_POST, true));
				assert(
					curl_setopt(
						$curl,
						\CURLOPT_POSTFIELDS,
						$requestProperties->getPayload()
					)
				);
				assert(curl_setopt($curl, \CURLOPT_URL, $requestProperties->getURL()));
				break;

			default:
				throw new \LogicException(
					"Invalid requestType value:".PHP_EOL.
					$requestProperties
				);
		}

		$contentTypeHeader = '';
		switch($requestProperties->getContentType()){
			case ContentType::TextHTML:
				$contentTypeHeader = 'Content-type: text/html; charset=UTF-8';
				break;

			case ContentType::MultipartForm:
				$contentTypeHeader = 'Content-type: multipart/form-data; charset=UTF-8';
				break;

			case ContentType::JSON:
				$contentTypeHeader = 'Content-type: application/json; charset=UTF-8';
				break;

			default:
				throw new \LogicException(
					"Invalid contentType value:".PHP_EOL.
					$requestProperties
				);
		}

		$headers = $requestProperties->getCustomHeaders();
		$headers[] = $contentTypeHeader;

		assert(curl_setopt($curl, \CURLOPT_HTTPHEADER, $headers));
	}

	public function request(HTTPRequestProperties $requestProperties): HTTPResponse {
		$curl = $this->getCurl();

		$this->setRequestOptions($curl, $requestProperties);

		$this->requestResponseTracer->logEvent(
            __FILE__, __LINE__,
            PHP_EOL . strval($requestProperties) . PHP_EOL
		);
		
		$result = $this->executeCurl($curl);
		
		$this->requestResponseTracer->logEvent(
			__FILE__, __LINE__,
			'Response received:'.PHP_EOL.
			strval($result).PHP_EOL
		);

		return $result;
	}
	
	private function executeMultiCurl(): void {
		do{
			curl_multi_exec($this->getMultiCurl(), $active);
		}while($active > 0);
	}

	public function multiRequest(array $requestsProperties): array {
		$this->curlPool->reserve(count($requestsProperties));

		$handleIndex = 0;
		$requestHandles = array();
		foreach($requestsProperties as $requestId => $requestProperties){
			$curl = $this->curlPool->getByIndex($handleIndex);
			self::initializeCurl($curl);
			$this->setRequestOptions($curl, $requestProperties);

			$this->requestResponseTracer->logEvent(
                __FILE__, __LINE__,
                strval($requestProperties)
			);

			$requestHandles[$requestId] = $curl;

			++$handleIndex;
		}

		foreach($requestHandles as $requestId => $curl){
			curl_multi_add_handle($this->getMultiCurl(), $curl);
		}
		
		$this->executeMultiCurl();

		$responses = array();

		foreach($requestHandles as $requestId => $curl){	
			$responseCode = intval(curl_getinfo($curl, \CURLINFO_HTTP_CODE));
			$responseBody = curl_multi_getcontent($curl);

			$responses[$requestId] = new HTTPResponse($responseCode, $responseBody);
		}

		return $responses;			
	}
}
