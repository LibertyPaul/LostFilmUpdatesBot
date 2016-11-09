<?php
require_once(__DIR__."/config/stuff.php");
require_once(__DIR__.'/TelegramBotFactory.php');
require_once(__DIR__."/Exceptions/StdoutTextException.php");


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
	
	protected function generateNotificationText($showTitleRu, $season, $seriesNumber, $seriesTitle){
		$template = join("\n", array(
			'Вышла новая серия #showName',
			'#season-й сезон, серия №#seriesNumber "#seriesTitle"',
			'Серию можно скачать по ссылке:',
			'https://www.lostfilm.tv/browse.php'
		));
			
		
		$text = str_replace(
			array('#showName', '#season', '#seriesNumber', '#seriesTitle'),
			array($showTitleRu, $season, $seriesNumber, $seriesTitle),
			$template
		);
		
		return $text;
	}

	public function newSeriesEvent($telegram_id, $title_ru, $season, $seriesNumber, $seriesTitle){
		assert(is_int($telegram_id));	
		$path = __DIR__.'/logs/newSeriesEventLog.txt';
		$logFile = createOrOpenLogFile($path);
		
		$notificationText = $this->generateNotificationText($title_ru, $season, $seriesNumber, $seriesTitle);
		
		try{
			$bot = $this->getBot($telegram_id);
			return $bot->sendMessage(
				array(
					'text' => $notificationText
			
				)
			);
		}
		catch(UserBlockedBotException $ubbe){
			return 403;
		}
		catch(Exception $ex){
			$res = fwrite($logFile, "exception: {$ex->getMessage()}\n");
			assert($res);
		}
		
		$res = fclose($logFile);
		assert($res);
	}
	
	
	
	protected function getUserInfo($user_id){
		$this->getUserInfoQuery->execute(
			array(
				':user_id' => $user_id
			)
		);
		
		$res = $this->getUserInfoQuery->fetch();
		if($res === false){
			throw new StdoutTextException("User with this id wasn't found");
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


















