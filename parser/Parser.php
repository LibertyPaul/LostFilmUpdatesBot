<?php

namespace parser;

require_once(__DIR__.'/../lib/HTTPRequester/HTTPRequesterInterface.php');

class SourceNotAvailableException extends \RuntimeException {}

abstract class Parser{
	protected $pageSrc;
	protected $srcEncoding;
	private $requester;

	public function __construct(
		\HTTPRequester\HTTPRequesterInterface $requester,
		$srcEncoding = null
	){
		$this->requester = $requester;
		$this->srcEncoding = $srcEncoding;
		$this->pageSrc = null;
	}
	
	public function loadSrc(string $url, array $customHeaders = array()){
		$requestProperties = new \HTTPRequester\HTTPRequestProperties(
			\HTTPRequester\RequestType::Get,
			\HTTPRequester\ContentType::TextHTML,
			$url,
			'',
			$customHeaders
		);
		
		try{
			$result = $this->requester->request($requestProperties);
		}
		catch(\HTTPRequester\HTTPTimeoutException $ex){
			throw new SourceNotAvailableException("timeout");
		}
		
		if($result->getCode() !== 200){
			throw new \RuntimeException(
				"HTTP call has failed:".PHP_EOL.
				$result
			);
		}
				
		$this->pageSrc = $result->getBody();
		
		if($this->srcEncoding !== null){
			$this->pageSrc = mb_convert_encoding($this->pageSrc, 'UTF-8', $this->srcEncoding);
		}
	}

	public function getSrc(){
		return $this->pageSrc;
	}

	abstract public function run();
}
