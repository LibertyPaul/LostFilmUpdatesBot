<?php
require_once(__DIR__.'/TracerBase.php');

class EchoTracer extends TracerBase{
	public function __construct($traceName){
		parent::__construct($traceName);
	}

	public function __destruct(){
		parent::__destruct();
	}

	protected function write($text){
		echo $text.PHP_EOL;
	}
}
