<?php

namespace HTTPRequester;

class HTTPResponse{
	private $code;
	private $body;

	public function __construct(int $code, string $body = null){
		$this->code = $code;
		$this->body = $body;
	}

	public function isSuccess(){
		return $this->code >= 200 && $this->code < 300;
	}

	public function isError(){
		return $this->code >= 400;
	}

	public function isErrorTooFrequent(){
		return $this->code === 429;
	}

	public function getBody(){
		return $this->body;
	}

	private static function prettifyIfPossible($text){
		$obj = json_decode($text);
		if($obj === null){
			return $text;
		}

		return json_encode($obj, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE);
	}

	public function __toString(){
		$result  = sprintf('HTTP Code: [%d]', $this->code).PHP_EOL;
		$result .= "Response Body:".PHP_EOL;
		$result .= self::prettifyIfPossible($this->getBody());

		return $result;
	}
}

