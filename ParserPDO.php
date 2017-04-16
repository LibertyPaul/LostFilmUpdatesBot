<?php

require_once(__DIR__.'/config/config.php');

class ParserPDO{
	private static $instance = null;

	private static function initialize(){
		self::$instance = new PDO(
			'mysql:dbname='.db_name.';host=localhost;charset=utf8mb4',
			db_parser_username,
			db_parser_password,
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
