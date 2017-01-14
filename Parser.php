<?php

require_once(__DIR__.'/HTTPRequesterInterface.php');

abstract class Parser{
	protected $pageSrc;
	protected $srcEncoding;
	private $requester;

	public function __construct(HTTPRequesterInterface $requester, $srcEncoding = null){
		assert($requester !== null);
		
		$this->requester = $requester;
		$this->srcEncoding = $srcEncoding;
		$this->pageSrc = null;
	}
	
	public function loadSrc($url){
		$result = $this->requester->sendGETRequest($url);
		
		if($result['code'] !== 200){
			throw new HTTPException('sendGETRequest has failed with code '.$result['code']);
		}
				
		if($this->srcEncoding === null){
			$this->pageSrc = $result['value'];
		}
		else{
			$this->pageSrc = mb_convert_encoding($result['value'], 'UTF-8', $this->srcEncoding);
		}
	}
	
	abstract public function run();
}
