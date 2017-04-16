<?php

require_once(__DIR__.'/../config/config.php');

class OwnerPDO{
	private static $instance = null;

	private static function initialize(){
		self::$instance = new PDO(
			'mysql:dbname='.db_name.';host=localhost;charset=utf8mb4',
			db_owner_username,
			db_owner_password,
			array(
				PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
			)
		);
	}

	public static function getInstance(){
		if(self::$instance === null){
			self::initialize();
		}

		return self::$instance;
	}
}
