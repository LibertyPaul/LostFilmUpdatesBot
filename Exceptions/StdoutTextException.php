<?php
require_once(__DIR__."/StdoutException.php");

class StdoutTextException extends StdoutException{
	public function release(){
		echo $this->getMessage()."\n";
	}
}
