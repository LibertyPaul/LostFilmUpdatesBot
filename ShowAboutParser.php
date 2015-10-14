<?php
require_once(realpath(dirname(__FILE__)).'/Parser.php');

class ShowAboutParser extends Parser{
	public function __construct($pageEncoding = "utf-8"){
		parent::__construct($pageEncoding);
	}
	
	public function run(){
		$regexp = '/Статус: ([^<]*)/';
		$params = array();
		$res = preg_match_all($regexp, $this->pageSrc, $params);
		if($res === false)
			throw new Exception("Статус сериала не найден");
		
		
		switch($params[1][0]){
			case 'закончен':
				return false;
			case 'снимается';
				return true;
			default:
				throw new Exception("unknown parameter: ".$params[1][0]);
		}
	}
}
		
		
