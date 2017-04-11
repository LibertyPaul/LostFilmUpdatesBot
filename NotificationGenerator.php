<?php
require_once(__DIR__.'/BotPDO.php');
require_once(__DIR__.'/Message.php');

class NotificationGenerator{
	private $getUserInfoQuery;
	private $getUserCountQuery;
	
	public function __construct(){
		$pdo = BotPDO::getInstance();
		
		$this->getUserInfoQuery = $pdo->prepare('
			SELECT *
			FROM `users`
			WHERE `id` = :user_id
		');
		
		$this->getUserCountQuery = $pdo->prepare('
			SELECT COUNT(*) AS count
			FROM `users`
		');
	}
	
	private static function generateNewSeriesNotificationText($showTitleRu, $season, $seriesNumber, $seriesTitle, $url){
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

	public function newSeriesEvent($telegram_id, $title_ru, $season, $seriesNumber, $seriesTitle, $URL){
		assert(is_int($telegram_id));
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

		$message = new Message(
			$telegram_id,
			$notificationText,
			'HTML',
			true
		);

		return $message;
	}

	protected function getUserInfo($user_id){
		$this->getUserInfoQuery->execute(
			array(
				':user_id' => $user_id
			)
		);
		
		$res = $this->getUserInfoQuery->fetch();
		if($res === false){
			throw new Exception("User with id($user_id) wasn't found");
		}
		
		return $res;
	}

	private static function messageToAdmin($text){
		assert(is_string($text));

		$message = new Message(
			2768837,
			$text
		);

		return $message;
	}

	public function newUserEvent($user_id){
		$userInfo = $this->getUserInfo($user_id);
		
		$this->getUserCountQuery->execute();
		$userCount = $this->getUserCountQuery->fetch()['count'];

		return self::messageToAdmin("Новый юзер $userInfo[telegram_firstName] [#$userCount]");
	}

	public function userLeftEvent($user_id){
		$userInfo = $this->getUserInfo($user_id);
		return self::messageToAdmin("Юзер $userInfo[telegram_firstName] удалился");
	}

}


















