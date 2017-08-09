<?php

namespace core;

require_once(__DIR__.'/BotPDO.php');
require_once(__DIR__.'/../lib/Config.php');
require_once(__DIR__.'/OutgoingMessage.php');
require_once(__DIR__.'/DirectedOutgoingMessage.php');

class NotificationGenerator{
	private $config;
	private $getUserFirstNameQuery;
	private $getUserCountQuery;
	
	public function __construct(){
		$pdo = \BotPDO::getInstance();

		$this->config = new \Config($pdo);
		
		$this->getUserFirstNameQuery = $pdo->prepare("
			SELECT `first_name`
			FROM `telegramUserData`
			WHERE `user_id` = :user_id
		");
		#TODO: do common way for different APIs
		
		$this->getUserCountQuery = $pdo->prepare('
			SELECT COUNT(*) AS count
			FROM `users`
		');
	}
	
	private static function generateNewSeriesNotificationText(
		$showTitleRu	,
		$season			,
		$seriesNumber	,
		$seriesTitle	,
		$url
	){
		$template = 
			'Вышла новая серия <b>#showName</b>'				.PHP_EOL.
			'Сезон #season, серия #seriesNumber, "#seriesTitle"'.PHP_EOL.
			'Серию можно скачать по ссылке:'					.PHP_EOL.
			'#URL'
		;
			
		
		$text = str_replace(
			array('#showName', '#season', '#seriesNumber', '#seriesTitle', '#URL'),
			array(
				htmlspecialchars($showTitleRu),
				$season,
				$seriesNumber,
				htmlspecialchars($seriesTitle),
				$url
			),
			$template
		);
		
		return $text;
	}

	public function newSeriesEvent(
		$title_ru		,
		$season			,
		$seriesNumber	,
		$seriesTitle	,
		$URL
	){
		assert(is_string($title_ru));
		assert(is_int($season));
		assert(is_int($seriesNumber));
		assert(is_string($seriesTitle));
		assert(is_string($URL));
		
		$notificationText = self::generateNewSeriesNotificationText(
			$title_ru,
			$season,
			$seriesNumber,
			$seriesTitle,
			$URL
		);

		return new OutgoingMessage(
			$notificationText,
			true
		);
	}

	protected function getUserFirstName($user_id){
		$this->getUserFirstNameQuery->execute(
			array(
				':user_id' => $user_id
			)
		);
		
		$res = $this->getUserFirstNameQuery->fetch();
		if($res === false){
			throw new Exception("User with id($user_id) wasn't found");
		}
		
		return $res['first_name'];
	}

	private function messageToAdmin($text){
		assert(is_string($text));

		$admin_id = $this->config->getValue('Admin Notifications', 'Admin Id');
		if($admin_id === null){
			return null;
		}

		return new DirectedOutgoingMessage(
			intval($admin_id),
			new OutgoingMessage($text)
		);
	}
	
	public function newUserEvent($user_id){
		$newUserEventEnabled = $this->config->getValue(
			'Admin Notifications',
			'Send New User Event'
		);

		if($newUserEventEnabled !== 'Y'){
			return null;
		}

		$userFirstName = $this->getUserFirstName($user_id);
		
		$this->getUserCountQuery->execute();
		$userCount = $this->getUserCountQuery->fetch()['count'];

		return $this->messageToAdmin("Новый юзер $userFirstName [#$userCount]");
	}

	public function userLeftEvent($user_id){
		$userLeftEventEnabled = $this->config->getValue(
			'Admin Notifications',
			'Send User Left Event'
		);

		if($userLeftEventEnabled !== 'Y'){
			return null;
		}

		$userFirstName = $this->getUserFirstName($user_id);
		return $this->messageToAdmin("Юзер $userFirstName удалился");
	}

}


















