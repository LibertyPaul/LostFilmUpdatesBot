<?php

namespace HTTPRequester;

class HTTPResponse{
	private $code;
	private $body;

	public function __construct($code, $body = null){
		assert(is_int($code));
		assert(is_string($body) || $body === null);

		$this->code = $code;
		$this->body = $body;
	}

	public function getCode(){
		return $this->code;
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
		$result = sprintf('HTTP Code: [%d], Response Body:', $this->getCode()).PHP_EOL;
		$result .= self::prettifyIfPossible($this->getBody());
		return $result;
	}
}

