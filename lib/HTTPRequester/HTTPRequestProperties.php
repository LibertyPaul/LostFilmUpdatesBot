<?php

namespace HTTPRequester;

abstract class RequestType{
	const Get = 1;
	const Post = 2;
}

abstract class ContentType{
	const TextHTML = 1;
	const MultipartForm = 2;
	const JSON = 3;
}

class HTTPRequestProperties{
	private $requestType;
	private $contentType;
	private $URL;
	private $payload;
	private $customHeaders;

	public function __construct(
		$requestType,
		$contentType,
		$URL,
		$payload = '',
		array $customHeaders = array()
	){
		if(
			$requestType !== RequestType::Get &&
			$requestType !== RequestType::Post
		){
			throw new \LogicException("Incorrect Request Type: $requestType");
		}

		if(
			$contentType !== ContentType::TextHTML		&&
			$contentType !== ContentType::MultipartForm	&&
			$contentType !== ContentType::JSON
		){
			throw new \LogicException("Incorrect Content Type: $contentType");
		}

		if(is_string($URL) === false){
			throw new \LogicException('Incorrect URL type: '.gettype($URL));
		}

		if(is_string($payload) === false && is_array($payload) === false){
			throw new \LogicException('Incorrect Payload type: '.gettype($payload));
		}
		
		$this->requestType = $requestType;
		$this->contentType = $contentType;
		$this->URL = $URL;
		$this->payload = $payload;
		$this->customHeaders = array();
		
		foreach($customHeaders as $header){	
			if(is_string($header) === false){
				throw new \LogicException('Incorrect Header Type: '.gettype($header));
			}

			$this->customHeaders[] = $header;
		}
	}

	public function getRequestType(){
		return $this->requestType;
	}

	public function getContentType(){
		return $this->contentType;
	}

	public function getURL(){
		return $this->URL;
	}

	public function getPayload(){
		return $this->payload;
	}

	public function getCustomHeaders(){
		return $this->customHeaders;
	}

	public function __toString(){
		switch($this->getRequestType()){
			case RequestType::Get:
				$requestTypeStr = 'Get';
				break;

			case RequestType::Post:	
				$requestTypeStr = 'Post';
				break;

			default:
				$requestTypeStr = 'Unknown';
				break;
		}

		switch($this->getContentType()){
			case ContentType::TextHTML:
				$contentTypeStr = 'TextHTML';
				break;

			case ContentType::MultipartForm:
				$contentTypeStr = 'MultipartForm';
				break;

			case ContentType::JSON:
				$contentTypeStr = 'JSON';
				break;

			default:
				$contentTypeStr = 'Unknown';
				break;
		}

		if(is_string($this->getPayload())){
			$payloadStr = $this->getPayload();
		}
		else{
			$payloadStr = print_r($this->getPayload(), true);
		}

		$result  = '/************[HTTP Request Properties]*************/'				.PHP_EOL;
		$result .= sprintf('URL:          [%s]'		, $this->getURL())					.PHP_EOL;
		$result .= sprintf('Request Type: [%s]'		, $requestTypeStr)					.PHP_EOL;
		$result .= sprintf('Content Type: [%s]'		, $contentTypeStr)					.PHP_EOL;
		$result .= sprintf('Payload:      [%d][%s]'	, strlen($payloadStr), $payloadStr)	.PHP_EOL;
		$result .= sprintf('Custom Headers (%d):'	, count($this->customHeaders))		.PHP_EOL;
		$result .= join(PHP_EOL, $this->customHeaders)									.PHP_EOL;
		$result .= '/**************************************************/';

		return $result;
	}
}

