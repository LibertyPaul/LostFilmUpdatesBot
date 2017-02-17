<?php
require_once(__DIR__.'/config/stuff.php');
require_once(__DIR__.'/TelegramBotFactory.php');
require_once(__DIR__.'/Exceptions/StdoutTextException.php');


class Notifier{
	protected $getUserInfoQuery;
	protected $getUserCountQuery;
	protected $telegramBotFactory;
	private $bots;
	
	
	public function __construct(TelegramBotFactoryInterface $telegramBotFactory){
		assert($telegramBotFactory !== null);
		$this->telegramBotFactory = $telegramBotFactory;
		
		$this->bots = array();
		
		$pdo = createPDO();
		
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
	
	private function getBot($telegram_id){
		assert(is_int($telegram_id));
		
		if(array_key_exists($telegram_id, $this->bots) === false){
			$this->bots[$telegram_id] = $this->telegramBotFactory->createBot($telegram_id);
		}
		
		return $this->bots[$telegram_id];
	}
	
	protected function getRecipients($show_id){ // returns list of telegram ids
		$this->usersToNotifyQuery->execute(
			array(
				':show_id' => $show_id
			)
		);

		return $this->usersToNotifyQuery->fetchAll(PDO::FETCH_COLUMN);
	}
	
	protected function generateNotificationText($showTitleRu, $season, $seriesNumber, $seriesTitle, $url){
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

	public function newSeriesEvent($telegram_id, $title_ru, $season, $seriesNumber, $seriesTitle, $url){
		assert(is_int($telegram_id));
		assert(is_int($season));
		assert(is_int($seriesNumber));
		
		$notificationText = $this->generateNotificationText(
			$title_ru,
			$season,
			$seriesNumber,
			$seriesTitle,
			$url
		);
		
		$bot = $this->getBot($telegram_id);
		return $bot->sendMessage(
			array(
				'text' => $notificationText,
				'parse_mode' => 'HTML',
				'disable_web_page_preview' => true,
			)
		);
		
	}
	
	
	
	protected function getUserInfo($user_id){
		$this->getUserInfoQuery->execute(
			array(
				':user_id' => $user_id
			)
		);
		
		$res = $this->getUserInfoQuery->fetch();
		if($res === false){
			throw new StdoutTextException("User with id($user_id) wasn't found");
		}
		
		return $res;
	}
	
	public function newUserEvent($user_id){
		$userInfo = $this->getUserInfo($user_id);
		
		$this->getUserCountQuery->execute();
		$userCount = $this->getUserCountQuery->fetchAll()[0]['count'];
		
		$bot = $this->getBot(2768837);
		$bot->sendMessage(
			array(
				'text' => "Новый юзер $userInfo[telegram_firstName] [#$userCount]"
			)
		);
	}
	
	public function userLeftEvent($user_id){
		$userInfo = $this->getUserInfo($user_id);
		
		$bot = $this->getBot(2768837);
		$bot->sendMessage(
			array(
				'text' => "Юзер $userInfo[telegram_firstName] удалился"
			)
		);
	}
}


















