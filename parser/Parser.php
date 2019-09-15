<?php

namespace parser;

require_once(__DIR__.'/../lib/HTTPRequester/HTTPRequesterInterface.php');

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
	
	public function loadSrc($url, $customHeaders = array()){
		$requestProperties = new \HTTPRequester\HTTPRequestProperties(
			\HTTPRequester\RequestType::Get,
			\HTTPRequester\ContentType::TextHTML,
			$url,
			'',
			$customHeaders
		);
		

		$result = $this->requester->request($requestProperties);
		
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
