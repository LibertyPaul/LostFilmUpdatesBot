<?php

class UserException extends Exception{
	public function __construct($text){
		parent::__construct($text);
	}
}
