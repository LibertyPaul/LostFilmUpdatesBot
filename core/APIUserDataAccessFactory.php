<?php

namespace core;

require_once(__DIR__.'/BotPDO.php');
require_once(__DIR__.'/../lib/DAL/APIUserDataInterface/APIUserDataAccess.php');
require_once(__DIR__.'/../TelegramAPI/DAL/TelegramUserDataAccess/TelegramUserDataAccess.php');

class APIUserDataAccessFactory{
	private static $instance;

	private static function createTelegramAPIUserDataAccess(){
		$pdo = \BotPDO::getInstance();
		
		$userDataAccess = new \DAL\TelegramUserDataAccess($pdo);
		
		if($userDataAccess instanceof \DAL\APIUserDataAccess == false){
			throw new \LogicException(
				'\DAL\TelegramUserDataAccess is not an instance of \DAL\APIUserDataAccess.'
			);
		}

		return $userDataAccess;
	}

	public static function getInstance(){
		if(isset($instance) === false){
			$instance = array(
				'TelegramAPI' => self::createTelegramAPIUserDataAccess()
			);
		}

		return $instance;
	}
}
