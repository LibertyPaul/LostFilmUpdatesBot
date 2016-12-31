<?php
require_once(__DIR__."/StdoutException.php");

class StdoutJSONException extends StdoutException{
	public function release(){
		echo json_encode(
			$this->getMessage(),
			JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
		);
	}
}
