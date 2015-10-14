<?php
require_once(realpath(dirname(__FILE__))."/StdoutException.php");

class StdoutTextException extends StdoutException{
	public function showErrorText(){
		echo $this->getMessage()."\n";
	}
}
