<?php

namespace core;

require_once(__DIR__.'/BotPDO.php');
require_once(__DIR__.'/../lib/Config.php');
require_once(__DIR__.'/OutgoingMessage.php');
require_once(__DIR__.'/DirectedOutgoingMessage.php');

require_once(__DIR__.'/../lib/DAL/Users/UsersAccess.php');
require_once(__DIR__.'/../lib/DAL/Users/User.php');

class NotificationGenerator{
	private $config;
	private $usersAccess;
	private $getUserFirstNameQuery;
	
	public function __construct(){
		$pdo = \BotPDO::getInstance();

		$this->config = new \Config($pdo);
		
		$this->getUserFirstNameQuery = $pdo->prepare("
			SELECT `first_name`
			FROM `telegramUserData`
			WHERE `user_id` = :user_id
		");
		#TODO: do common way for different APIs
	}
	
	private function generateNewSeriesNotificationText(
		string $showTitleRu,
		int $season,
		int $seriesNumber,
		string $seriesTitle,
		string $URL
	){
		$template = 
			'<b>%s</b>: <b>S%02dE%02d</b> "%s"'	.PHP_EOL.
			'Серию можно скачать по ссылке:'	.PHP_EOL.
			'%s'
		;

		$torAdvice = 'Если не получается зайти на LostFilm: /about_tor';

		if($this->config->getValue('Notifications', 'Include Tor Advice') === 'Y'){
			$template .= PHP_EOL.PHP_EOL.$torAdvice;
		}

		return sprintf(
			$template,
			htmlspecialchars($showTitleRu),
			$season,
			$seriesNumber,
			htmlspecialchars($seriesTitle),
			$URL
		);
	}

	public function newSeriesEvent(
		string $title_ru,
		int $season,
		int $seriesNumber,
		string $seriesTitle,
		string $URL
	){
		$notificationText = $this->generateNewSeriesNotificationText(
			$title_ru,
			$season,
			$seriesNumber,
			$seriesTitle,
			$URL
		);

		return new OutgoingMessage(
			$notificationText,
			new MarkupType(MarkupTypeEnum::HTML)
		);
	}

	protected function getUserFirstName(int $user_id){
		$this->getUserFirstNameQuery->execute(
			array(
				':user_id' => $user_id
			)
		);
		
		$res = $this->getUserFirstNameQuery->fetch();
		if($res === false){
			throw new \Exception("User with id($user_id) wasn't found");
		}
		
		return $res['first_name'];
	}

	private function messageToAdmin(string $text){
		$admin_id = $this->config->getValue('Admin Notifications', 'Admin Id');
		if($admin_id === null){
			return null;
		}
		
		try{
			$admin = $this->usersAccess->getUserById(intval($admin_id));

			return new DirectedOutgoingMessage(
				$admin,
				new OutgoingMessage($text)
			);
		}
		catch(\Throwable $ex){
			return null;
		}
	}
	
	public function newUserEvent(int $user_id){
		$newUserEventEnabled = $this->config->getValue('Admin Notifications', 'Send New User Event');

		if($newUserEventEnabled !== 'Y'){
			return null;
		}
		
		try{
			$userFirstName = $this->getUserFirstName($user_id);
			$userCount = $this->usersAccess->getActiveUsersCount(false);
		}
		catch(\Throwable $ex){
			return null;
		}

		return $this->messageToAdmin("Новый юзер $userFirstName [#$userCount]");
	}

	public function userLeftEvent(int $user_id){
		$userLeftEventEnabled = $this->config->getValue('Admin Notifications', 'Send User Left Event');

		if($userLeftEventEnabled !== 'Y'){
			return null;
		}
		
		try{
			$userFirstName = $this->getUserFirstName($user_id);
		}
		catch(\Throwable $ex){
			return null;
		}

		return $this->messageToAdmin("Юзер $userFirstName удалился");
	}
}
