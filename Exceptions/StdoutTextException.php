<?php
require_once(realpath(dirname(__FILE__))."/StdoutException.php");

class StdoutTextException extends StdoutException{
	public function showErrorText(){
		echo $this->getMessage()."\n";
	}
	
		
	public function getMessage(){
		$dateTime = date('j.n.y g:i:s');
		$message = parent::getMessage();
		return "[$dateTime]\t$message";
	}
}
