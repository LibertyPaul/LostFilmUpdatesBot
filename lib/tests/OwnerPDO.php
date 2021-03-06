<?php

require_once(__DIR__.'/../PDOInit.php');

class OwnerPDO{
	private static $instance = null;

	public static function getInstance(){
		$credentialsFile = __DIR__.'/../../DBCredentials/Owner.ini';
		if(self::$instance === null){
			self::$instance = PDOInit::initialize($credentialsFile);
		}

		return self::$instance;
	}
}
