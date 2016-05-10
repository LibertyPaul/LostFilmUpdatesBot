<?php
require_once(__DIR__."/StdoutException.php");

class StdoutTextException extends StdoutException{
	public function showErrorText(){
		echo $this->getMessage()."\n";
	}
}
