<?php
require_once(realpath(dirname(__FILE__))."/StdoutException.php");

class StdoutJSONException extends StdoutException{
	public function showErrorText(){
		echo json_encode(
			$this->getMessage(),
			JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
		);
	}
}
