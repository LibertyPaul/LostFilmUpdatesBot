<?php
require_once(__DIR__."/config/stuff.php");
require_once(__DIR__.'/TelegramBotFactory.php');
require_once(__DIR__."/Exceptions/StdoutTextException.php");


class Notifier{
	protected $usersToNotifyQuery;
	protected $showTitleQuery;
	protected $getUserInfoQuery;
	protected $getUserCountQuery;
	protected $telegramBotFactory;
	
	
	public function __construct(TelegramBotFactoryInterface $telegramBotFactory){
		if($telegramBotFactory === null){
			throw new Exception('$telegramBotFactory should not be null');
		}
		
		$this->telegramBotFactory = $telegramBotFactory;
		
		$pdo = createPDO();
		
		$this->usersToNotifyQuery = $pdo->prepare('
			SELECT `telegram_id`
			FROM `users`
			RIGHT JOIN `tracks` ON `users`.`id` = `tracks`.`user_id`
			WHERE `users`.`mute` = 0
			AND `tracks`.`show_id` = :show_id
		');
		
		
		$this->showTitleQuery = $pdo->prepare('
			SELECT `title_ru`
			FROM `shows`
			WHERE `id` = :show_id
		');
		
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
	
	public function newSeriesEvent($show_id, $season, $seriesNumber, $seriesTitle){
		$this->showTitleQuery->execute(
			array(
				':show_id' => $show_id
			)
		);
		
		$res = $this->showTitleQuery->fetchAll();
		if(count($res) === 0){
			throw new StdoutException("Show with id = $show_id is not found");
		}

		$title_ru = $res[0]['title_ru'];
		
		$path = __DIR__.'/logs/newSeriesEventLog.txt';
		$logFile = createOrOpenLogFile($path);
		
		
		$report = str_replace(
			array('#DATETIME', '#TITLE_RU', '#SEASON', '#SERIES'),
			array(date('d.m.Y H:i:s'), $title_ru, $season, $seriesNumber),
			"[#DATETIME]\t#TITLE_RU - #SEASON:#SERIES\n"
		);
		
		$res = fwrite($logFile, $report);
		if($res === false){
			throw new StdoutTextException("fwrite error 1");
		}

		$usersToNotify = $this->getRecipients($show_id);
		$notificationText = $this->generateNotificationText($title_ru, $season, $seriesNumber, $seriesTitle);
		
		$usersCount = count($usersToNotify);
		$succeed = 0;
		$blockedByUserCount = 0;
		foreach($usersToNotify as $telegram_id){
			try{
				$bot = $this->telegramBotFactory->createBot((intval($telegram_id)));
				$bot->sendMessage(
					array(
						'text' => $notificationText
				
					)
				);
				++$succeed;
			}
			catch(UserBlockedBotException $ubbe){
				++$userBlockedCount;
			}
			catch(Exception $ex){
				$res = fwrite($logFile, "exception: {$ex->getMessage()}\n");
				if($res === false){
					throw new StdoutTextException("fwrite error 3");
				}
			}
		}
		
		$report = str_replace(
			array('#SUCCEED', '#COUNT', '#BLOCKED_BY_USER', '#FAILED'),
			array($succeed, $usersCount, $blockedByUserCount, $usersCount - $succeed),
			"#SUCCEED of #COUNT notifications have been sent. #BLOCKED_BY_USER of #FAILED have been blocked by user\n\n"
		);
		
		$res = fwrite($logFile, $report);
		if($res === false){
			throw new StdoutTextException("fwrite error 2");
		}
		
		$res = fclose($logFile);
		if($res === false){
			throw new StdoutTextException("fclose error");
		}
			
	}
	
	protected function getUserInfo($user_id){
		$this->getUserInfoQuery->execute(
			array(
				':user_id' => $user_id
			)
		);
		
		$res = $this->getUserInfoQuery->fetchAll();
		if(empty($res)){
			throw new StdoutTextException("User with this id wasn't found");
		}
		
		return $res[0];
	}
	
	public function newUserEvent($user_id){
		$userInfo = $this->getUserInfo($user_id);
		
		$this->getUserCountQuery->execute();
		$userCount = $this->getUserCountQuery->fetchAll()[0]['count'];
		
		$bot = $this->telegramBotFactory->createBot(2768837);
		$bot->sendMessage(
			array(
				'text' => "Новый юзер $userInfo[telegram_firstName] [#$userCount]"
			)
		);
	}
	
	public function userLeftEvent($user_id){
		$userInfo = $this->getUserInfo($user_id);
		
		$bot = $this->telegramBotFactory->createBot(2768837);
		$bot->sendMessage(
			array(
				'text' => "Юзер $userInfo[telegram_firstName] удалился"
			)
		);
	}
}


















