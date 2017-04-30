<?php
require_once(__DIR__.'/../lib/PDOInit.php');

class ParserPDO{
	private static $instance = null;

	public static function getInstance(){
		$credentialsFile = __DIR__.'/../DBCredentials/Parser.ini';
		if(self::$instance === null){
			self::$instance = PDOInit::initialize($credentialsFile);
		}

		return self::$instance;
	}
}
