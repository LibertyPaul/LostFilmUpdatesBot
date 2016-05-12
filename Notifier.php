<?php
require_once(__DIR__."/config/stuff.php");
require_once(__DIR__.'/TelegramBot.php');
require_once(__DIR__."/Exceptions/StdoutTextException.php");


class Notifier{
	protected $usersToNotifyQuery;
	protected $showTitleQuery;
	protected $getUserInfoQuery;
	protected $getUserCountQuery;
	
	
	public function __construct(){
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
		$res = fwrite($logFile, "[".date('d.m.Y H:i:s')."]\t$title_ru - $season:$seriesNumber\n");
		if($res === false){
			throw new StdoutTextException("fwrite error 1");
		}

		$usersToNotify = $this->getRecipients($show_id);
		$notificationText = $this->generateNotificationText($title_ru, $season, $seriesNumber, $seriesTitle);
		
		$usersCount = count($usersToNotify);
		$success = 0;
		foreach($usersToNotify as $telegram_id){
			try{
				$bot = new TelegramBot(intval($telegram_id));
				$bot->sendMessage(
					array(
						'text' => $notificationText
				
					)
				);
				++$success;
			}
			catch(StdoutTextException $ste){
				$res = fwrite($logFile, "exception: {$ste->getMessage()}\n");
				if($res === false){
					throw new StdoutTextException("fwrite error 2");
				}
			}
			catch(Exception $ex){
				$res = fwrite($logFile, "exception: {$ex->getMessage()}\n");
				if($res === false){
					throw new StdoutTextException("fwrite error 3");
				}
			}
		}
		
		$res = fwrite($logFile, "\n$success/$usersCount уведомлений разослано\n\n");
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
		
		$bot = new TelegramBot(2768837);
		$bot->sendMessage(
			array(
				'text' => "Новый юзер $userInfo[telegram_firstName] [#$userCount]"
			)
		);
	}
	
	public function userLeftEvent($user_id){
		$userInfo = $this->getUserInfo($user_id);
		
		$bot = new TelegramBot(2768837);
		$bot->sendMessage(
			array(
				'text' => "Юзер $userInfo[telegram_firstName] удалился"
			)
		);
	}
}


















