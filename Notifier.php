<?php
require_once(realpath(dirname(__FILE__))."/config/stuff.php");
require_once(realpath(dirname(__FILE__)).'/TelegramBot.php');
require_once(realpath(dirname(__FILE__))."/Exceptions/StdoutTextException.php");


class Notifier{
	protected $usersToNotifyQuery;
	protected $showTitleQuery;
	
	
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
		
		$path = realpath(dirname(__FILE__)).'/logs/newSeriesEventLog.txt';
		$logFile = createOrOpenLogFile($path);
		$res = fwrite($logFile, "[".date('d.m.Y H:i:s')."]\t$title_ru - $season:$seriesNumber");
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
}
