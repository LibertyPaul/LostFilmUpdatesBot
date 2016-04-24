<?php
require_once(realpath(dirname(__FILE__))."/TelegramBot_base.php");
require_once(realpath(dirname(__FILE__))."/Exceptions/TelegramException.php");
require_once(realpath(dirname(__FILE__))."/Exceptions/StdoutTextException.php");
require_once(realpath(dirname(__FILE__))."/config/config.php");
require_once(realpath(dirname(__FILE__))."/Botan/Botan.php");

class TelegramBot extends TelegramBot_base{
	protected $telegram_id;//от кого пришло сообщение
	protected $chat_id;//куда отвечать(возможно не на прямую к пользователю, а в чат, где он написал боту)
	protected $botan;
	
	public function __construct($telegram_id, $chat_id = null){
		if(is_int($telegram_id) === false)
			throw new StdoutTextException("incorrect telegram_id");
		
		if($chat_id === null)
			$chat_id = $telegram_id;
		
		if(is_int($chat_id) === false)
			throw new StdoutTextException("incorrect chat_id");
				
		TelegramBot_base::__construct();
		$this->telegram_id = $telegram_id;
		$this->chat_id = $chat_id;
		$this->botan = new Botan(BOTAN_API_KEY);
	}	
	
	public function sendMessage($args){//function is called by TelegramException and not being able to throw another TelegramException
		try{
			if(isset($args['chat_id']) === false)
				$args['chat_id'] = $this->chat_id;
			parent::sendMessage($args);
		}
		catch(TelegramException $tex){//если даже произошло исключение TelegramException - перенаправляем его в stdout
			throw new StdoutTextException($tex->getMessage());
		}
	}
	
	protected function getPreviousMessageArrayKey(){
		return MEMCACHE_MESSAGE_CHAIN_PREFIX.$this->telegram_id;
	}
	
	protected function getPreviousMessageArray(){
		$previousMessageArray_serialized = $this->memcache->get($this->getPreviousMessageArrayKey());
		if($previousMessageArray_serialized === false)
			return array();
		else
			return unserialize($previousMessageArray_serialized);
	}
	
	protected function getArgc(){
		return count($this->getPreviousMessageArray());//TODO оптимизировать
	}
	
	protected function addPreviousMessageArray($messageText){
		$previousMessageArray_serialized = $this->memcache->get($this->getPreviousMessageArrayKey());
		if($previousMessageArray_serialized === false)
			$previousMessageArray_serialized = serialize(array());
		
		$previousMessageArray = unserialize($previousMessageArray_serialized);
		array_push($previousMessageArray, $messageText);
		$previousMessageArray_serialized = serialize($previousMessageArray);
		
		$res = $this->memcache->set($this->getPreviousMessageArrayKey(), $previousMessageArray_serialized);
		if($res === false)
			throw new TelegramException($this->chat_id, "Memcache set() error");
	}
	
	protected function deletePreviousMessageArray(){
		$this->memcache->delete($this->getPreviousMessageArrayKey());
	}
	
	protected function getUserId(){//SQL injection-safe. returns an integer.
		static $user_id;
		if(isset($user_id) === false){
			$res = $this->sql->query("
				SELECT `id`
				FROM `users`
				WHERE `telegram_id` = {$this->telegram_id}
			");
			if($res->num_rows === 0){
				$this->deletePreviousMessageArray();			
				throw new TelegramException($this->chat_id, "Твой Telegram ID не найден в БД, ты регистрировался командой /start ?");
			}
		
			$user = $res->fetch_object();
			$user_id = intval($user->id);
		}
		return $user_id;
	}
	
	protected function updateUserInfo($telegram_username, $telergam_firstName){
		$telegram_username = $this->sql->real_escape_string($telegram_username);
		$telergam_firstName = $this->sql->real_escape_string($telergam_firstName);
		$res = $this->sql->query("
			UPDATE `users`
			SET 
				`telegram_username` = '$telegram_username',
				`telergam_firstName` = '$telergam_firstName'
			WHERE `id` = {$this->getUserId()}
		");
		if($this->sql->affected_rows === 0)
			throw new StdoutTextException("updateUserInfo -> sql UPDATE error");
	}
	
	protected function notifyAdmin($text){
		$res = $this->sql->query("
			SELECT `telegram_id`
			FROM `users`
			WHERE `telegram_username` LIKE 'LibertyPaul'
		");
		if($res->num_rows === 0)//админа нет в БД
			return;
		
		$admin = $res->fetch_object();
		
		$adminBot = new TelegramBot(intval($admin->telegram_id), intval($admin->telegram_id));
		$adminBot->sendMessage(
			array(
				'text' => $text,
				'reply_markup' => array(
					'hide_keyboard' => true
				)
			)
		);
	}
	
	
	
	
	public static function newSeriesEvent($show_id, $season, $seriesNumber, $seriesTitle){
		if(is_int($show_id) === false)
			throw new StdoutTextException("show_id must be an integer");
		
		
	
		$sql = createSQL();
		$usersToNotify = $sql->query("
			SELECT `telegram_id`
			FROM `users`
			RIGHT JOIN `tracks` ON `users`.`id` = `tracks`.`user_id`
			WHERE `tracks`.`show_id` = $show_id
			AND `users`.`mute` = 0
		");
		
		$res = $sql->query("
			SELECT `title_ru`, `url_id`
			FROM `shows`
			WHERE `id` = $show_id
		");
		if($res->num_rows === 0)
			throw new StdoutException("Show with id = $show_id is not found");
		$show = $res->fetch_object();
		
		$text = "Вышла новая серия {$show->title_ru}\n";
		$text .= "$season-й сезон, серия №$seriesNumber \"$seriesTitle\"\n";
		$text .= "Серию можно скачать по ссылке:\nhttps://www.lostfilm.tv/browse.php";
		
		
		$path = realpath(dirname(__FILE__)).'/logs/newSeriesEventLog.txt';
		$logFile = createOrOpenLogFile($path);
		
		$res = fwrite($logFile, "[".date('d.m.Y H:i:s')."]\t{$show->title_ru} - $season:$seriesNumber");
		if($res === false)
			throw new StdoutTextException("fwrite error 1");
		
		$usersCount = $usersToNotify->num_rows;
		$i = 0;
		while($user = $usersToNotify->fetch_object()){
			try{
				$bot = new TelegramBot(intval($user->telegram_id));
				$bot->sendMessage(
					array(
						'text' => $text
				
					)
				);
				++$i;
			}
			catch(StdoutTextException $ste){
				$res = fwrite($logFile, "exception: {$ste->getMessage()}\n");
				if($res === false)
					throw new StdoutTextException("fwrite error 2");
			}
			catch(Exception $ex){
				$res = fwrite($logFile, "exception: {$ex->message}\n");
				if($res === false)
					throw new StdoutTextException("fwrite error 3");
			}
		}
		
		$res = fwrite($logFile, "\n$i/$usersCount уведомлений разослано\n\n");
		if($res === false)
			throw new StdoutTextException("fwrite error 2");
		$res = fclose($logFile);
		if($res === false)
			throw new StdoutTextException("fclose error");
			
	}
	
	protected function insertOrDeleteShow($in_out_flag){//$in_out_flag
		$fullMessageArray = $this->getPreviousMessageArray();
		$argc = count($fullMessageArray);
		
		$successText = "";
		$action = null;
		if($in_out_flag){
			$successText = "добавлен";
			$action = $this->pdo->prepare("
				INSERT INTO `tracks` (`user_id`, `show_id`) 
				VALUES ({$this->getUserId()}, :show_id)
			");
		}					
		else{
			$successText = "удален";
			$action = $this->pdo->prepare("
				DELETE FROM `tracks`
				WHERE `show_id` = :show_id
				AND `user_id` = {$this->getUserId()}
			");
		}
	
	
		switch($argc){
		case 1:
			if($in_out_flag){//true -> add_show, false -> remove show
				$query = "
					SELECT
						CONCAT(
							`title_ru`,
							' (',
							`title_en`,
							')'
						) AS `title`
					FROM `shows`
					LEFT JOIN(
						SELECT `tracks`.`id`, `tracks`.`show_id`
						FROM `tracks`
						JOIN `shows` ON `tracks`.`show_id` = `shows`.`id`
						WHERE `tracks`.`user_id` = {$this->getUserId()}
					) AS `tracked`
					ON `shows`.`id` = `tracked`.`show_id`
					WHERE `tracked`.`id` IS NULL
					AND `shows`.`onAir` != 0
					ORDER BY `title`
				";
			}
			else{				
				$query = "
					SELECT
						CONCAT(
							`title_ru`,
							' (',
							`title_en`,
							')'
						) AS `title`
					FROM `tracks`
					JOIN `shows` ON `tracks`.`show_id` = `shows`.`id`
					WHERE `tracks`.`user_id` = {$this->getUserId()}
					ORDER BY `title`
				";
			}
			
			$res = $this->sql->query($query);
			if($res->num_rows === 0){
				$this->deletePreviousMessageArray();
				
				$reply = null;
				if($in_out_flag)
					$reply = "Ты уже добавил все (!) сериалы из списка. И как ты успеваешь их смотреть??";
				else
					$reply = "Нечего удалять";
				
				$this->sendMessage(
					array(
						'text' => $reply,
						'reply_markup' => array(
							'hide_keyboard' => true
						)
					)
				);
			}
			else{
				$showTitles = array();
				while($show = $res->fetch_object())
					$showTitles[] = $show->title;
				$keyboard = $this->createKeyboard($showTitles);
				
				$this->sendMessage(
					array(
						'text' => "Как называется сериал?\nВыбери из списка или введи пару слов из названия.",
						'reply_markup' => array(
							'keyboard' => $keyboard,
							'one_time_keyboard' => true
						)
					)
				);
			}
			break;
		case 2:
			$showName = $this->sql->real_escape_string($fullMessageArray[1]);
			$res = $this->sql->query("
				SELECT `id`, CONCAT(
					`title_ru`,
					' (',
					`title_en`,
					')'
				) AS `title_all`
				FROM `shows`
				HAVING `title_all` LIKE '$showName'
			");
			if($res->num_rows > 0){//нашли совпадение по имени (пользователь нажал на кнопку или (, что маловероятно,) ввел сам точное название
				$show = $res->fetch_object();
				$title_all = $show->title_all;
				$res = $action->execute(
					array(
						':show_id' => $show->id
					)
				);
				if($res === false || $action->rowCount() === 0){
					$this->deletePreviousMessageArray();
					throw new TelegramException($this->chat_id, "Ошибка добавления в базу данных. Я сообщу об этом создателю");
				}
				else{//все норм
					$this->sendMessage(
						array(
							'text' => $title_all." $successText",
							'reply_markup' => array(
								'hide_keyboard' => true
							)
						)
					);
					$this->deletePreviousMessageArray();
				}
				
			}
			else{//совпадения не найдено. Возможно юзверь проебланил с названием. Придется угадывать.
				//TODO: если ненулевой результат у нескольких вариантов - дать пользователю выбрать
				if($in_out_flag){//true -> add_show, false -> remove show
					$query = "
						SELECT
							`shows`.`id` AS `id`,
							MATCH(`title_ru`, `title_en`) AGAINST('$showName') AS `score`,
							CONCAT(
								`title_ru`,
								' (',
								`title_en`,
								')'
							) AS `title`
						FROM `shows`
						LEFT JOIN(
							SELECT `tracks`.`id`, `tracks`.`show_id`
							FROM `tracks`
							JOIN `shows` ON `tracks`.`show_id` = `shows`.`id`
							WHERE `tracks`.`user_id` = {$this->getUserId()}
						) AS `tracked`
						ON `shows`.`id` = `tracked`.`show_id`
						WHERE `tracked`.`id` IS NULL
						AND `shows`.`onAir` != 0
						HAVING `score` > 0.1
						ORDER BY `score` DESC
					";
				}
				else{				
					$query = "
						SELECT
							`shows`.`id` AS `id`,
							MATCH(`title_ru`, `title_en`) AGAINST('$showName') AS `score`,
							CONCAT(
								`title_ru`,
								' (',
								`title_en`,
								')'
							) AS `title`
						FROM `tracks`
						JOIN `shows` ON `tracks`.`show_id` = `shows`.`id`
						WHERE `tracks`.`user_id` = {$this->getUserId()}
						HAVING `score` > 0.1
						ORDER BY `score` DESC
					";
				}
				
				$res = $this->sql->query($query);
				
				switch($res->num_rows){
				case 0://не найдено ни одного похожего названия
					$this->sendMessage(
						array(
							'text' => "Не найдено подходящих названий",
							'reply_markup' => array(
								'hide_keyboard' => true
							)
						)
					);
					$this->deletePreviousMessageArray();
					break;
								
				case 1://найдено только одно подходящее название
					$show = $res->fetch_object();
					$res = $action->execute(
						array(
							':show_id' => $show->id
						)
					);
					if($action->rowCount() > 0){
						$this->sendMessage(
							array(
								'text' => "{$show->title} $successText",
								'reply_markup' => array(
									'hide_keyboard' => true
								)
							)
						);
					}
					else{
						$this->deletePreviousMessageArray();
						throw new TelegramException($this->chat_id, "action->execute 2 error");
					}
					
					$this->deletePreviousMessageArray();
					break;
				
				default://подходят несколько вариантов
					$showTitles = array();
					while($predictedShow = $res->fetch_object()){
						$showTitles[] = $predictedShow->title;
					}
					$keyboard = $this->createKeyboard($showTitles);
				
					$this->sendMessage(
						array(
							'text' => "Какой из этих ты имел ввиду:",
							'reply_markup' => array(
								'keyboard' => $keyboard,
								'one_time_keyboard' => true
							)
						)
					);
					break;
				}
			}
			break;
		case 3:
			$exactShowName = $this->sql->real_escape_string($fullMessageArray[2]);
			$res = $this->sql->query("
				SELECT 
					`id`,
					CONCAT(
						`title_ru`,
						' (',
						`title_en`,
						')'
					) AS `title_all`,
					`title_ru`
				FROM `shows`
				HAVING `title_all` LIKE '$exactShowName'
			");
			if($res->num_rows === 0){
				$this->sendMessage(
					array(
						'text' => "Не могу найти такое название. Используй кнопки для выбора сериала.",
						'reply_markup' => array(
							'hide_keyboard' => true
						)
					)
				);
			}
			else{
				$show = $res->fetch_object();
				$res = $action->execute(
					array(
						':show_id' => $show->id
					)
				);
				if($action->rowCount() > 0){
					$this->sendMessage(
						array(
						'text' => "{$show->title_ru} $successText",
							'reply_markup' => array(
								'hide_keyboard' => true
							)
						)
					);
				}
				else{
					$this->deletePreviousMessageArray();
					throw new TelegramException($this->chat_id, "action->execute 3 error");
				}
			}
			$this->deletePreviousMessageArray();
			break;
		}
	}
/*
Commands:
help - Показать инфо о боте
add_show - Добавить уведомления о сериале
get_my_shows - Показать выбранные сериалы
remove_show - Удалить уведомления о сериале
mute - Выключить уведомления на время
cancel - Отменить команду
stop - Удалиться из контакт-листа бота
*/
	protected function sendToBotan($message, $eventName){
		$message_array = json_decode(json_encode($message), true);
		$this->botan->track($message_array, $eventName);
	}
	
	protected function extractCommand($text){//в чатах команда, посылаемая боту имеет вид /cmd@LostFilmUpdatesBot
		if(strlen($text) === 0){
			return '';
		}
	
		$regexp = '/([^@]+)[\s\S]*/';
		$matches = array();
		$res = preg_match($regexp, $text, $matches);
		if($res === false){
			$this->deletePreviousMessageArray();
			throw new TelegramException($this->chat_id, "Не знаю такой команды");
		}
		
		return $matches[1];
	}
	
	public function incomingUpdate($message){
		if($message->from->id !== $this->telegram_id)
			throw new StdoutTextException("update telegram id, and stored id doesn't match");
		
		if(isset($message->text) === false)
			return;
		
		$cmd = $this->extractCommand($message->text);
		
		if($cmd === "/cancel")
			$this->deletePreviousMessageArray();
		
		if($this->getArgc() === 0)
			$this->addPreviousMessageArray($cmd);
		else
			$this->addPreviousMessageArray($message->text);//добавляем сообщение к n предыдущих
		$messagesTextArray = $this->getPreviousMessageArray();//забираем n + 1 сообщений
		
		print_r($messagesTextArray);
		$argc = count($messagesTextArray);
		
		if($argc < 1)
			throw new StdoutTextException("PreviousMessageArray is empty");
		switch($messagesTextArray[0]){
		case "/start":
			$this->deletePreviousMessageArray();
			if(isset($message->from->username))
				$telegram_username = $this->sql->real_escape_string($message->from->username);
			else
				$telegram_username = '';
				
			if(isset($message->from->first_name))
				$telegram_firstName = $this->sql->real_escape_string($message->from->first_name);
			else
				$telegram_firstName = '';
			
			$res = $this->sql->query("
				INSERT IGNORE INTO `users` (`telegram_id`, `telegram_username`, `telergam_firstName`)
				VALUES ({$this->telegram_id}, '$telegram_username', '$telegram_firstName')
			");
			
			if($this->sql->affected_rows > 0){
				$startText = "Привет, ".$message->from->first_name."\n";
				$startText .= "Я - лостфильм бот, моя задача - оповестить тебя о выходе новых серий твоих любимых сериалов на сайте http://lostfilm.tv/\n\n";
				$startText .= "Чтобы узнать что я умею - введи /help или выбери эту команду в списке";
				$this->sendMessage(
					array(
						'text' => $startText,
						'disable_web_page_preview' => true,
					)
				);
				try{
					$this->notifyAdmin("Новый юзер {$message->from->first_name}");
				}
				catch(Exception $ex){
				}
				
			}
			else{
				$this->sendMessage(
					array(
						'text' => "Мы ведь уже знакомы, правда?"
					)
				);
			}
			
			break;
		case "/cancel":
			$this->deletePreviousMessageArray();
			$this->sendMessage(
				array(
					'text' => "Действие отменено.",
					'reply_markup' => array(
						'hide_keyboard' => true
					)
				)
			);
			break;
		case "/stop":
			$ANSWER_YES = 'Да';
			$ANSWER_NO = 'Нет';
			switch($argc){
			case 1:
				$keyboard = array(
					array(
						$ANSWER_YES,
						$ANSWER_NO
					)
				);
				$this->sendMessage(
					array(
						'text' => "Ты уверен? Вся информация о тебе будет безвозвратно потеряна...",
						'reply_markup' => array(
							'keyboard' => $keyboard,
							'one_time_keyboard' => true
						)
					)
				);
				
				break;
			
			case 2:
				$fullMessageArray = $this->getPreviousMessageArray();
				$this->deletePreviousMessageArray();
				$resp = $fullMessageArray[1];
				if($resp === $ANSWER_YES){
					$res = $this->sql->query("
						DELETE FROM `users`
						WHERE `id` = {$this->getUserId()}
					");
					if($this->sql->affected_rows === 0){
						$this->deletePreviousMessageArray();
						throw new TelegramException($this->chat_id, "Упс, произошло недоразумение. Я сообщу об этом создателю");
					}
					
					$this->sendMessage(
						array(
							'text' => "Прощай...",
							'reply_markup' => array(
								'hide_keyboard' => true
							)
						)
					);
					
					try{
						$this->notifyAdmin("Юзер {$message->from->first_name} удалился");
					}
					catch(Exception $ex){
					}
				}
				else if($resp === $ANSWER_NO){
					$this->sendMessage(
						array(
							'text' => "Фух, а то я уже испугался",
							'reply_markup' => array(
								'hide_keyboard' => true
							)
						)
					);
				}
				else{
					$this->sendMessage(
						array(
							'text' => "Что?",
							'reply_markup' => array(
								'hide_keyboard' => true
							)
						)
					);
				}
				break;
			}
			break;
		
		case "/help":
			$this->deletePreviousMessageArray();
			
			$helpText = "Лостфилм бот - бот, который оповещает о новых сериях на http://lostfilm.tv/\n\n";
			$helpText .= "Список команд:\n";
			$helpText .= "/add_show - Добавить уведомления о сериале\n";
			$helpText .= "/remove_show - Удалить уведомления о сериале\n";
			$helpText .= "/mute - Выключить уведомления на время\n";
			$helpText .= "/cancel - отменить команду\n";
			$helpText .= "/help - показать это сообщение\n\n";

			$helpText .= "Telegram создателя - @libertypaul\n";
			$helpText .= "Ну и электропочта есть, куда ж без неё: admin@libertypaul.ru\n\n";
			$helpText .= "Создатель бота не имеет никакого отношеня к проекту lostfilm.tv.";
			
			$this->sendMessage(
				array(
					'text' => $helpText,
					'disable_web_page_preview' => true,
					'reply_markup' => array(
						'hide_keyboard' => true
					)
				)
			);
			break;
		
		case "/mute":
			$this->deletePreviousMessageArray();
			$res = $this->sql->query("
				SELECT `mute`
				FROM `users`
				WHERE `id` = {$this->getUserId()}
			");
			if($res->num_rows === 0){
				throw new TelegramException($this->chat_id, "Упс, кажется я не могу найти тебя в списке пользователей.\nПопробуй выполнить команду /start и попробовать снова");
			}
			
			$user = $res->fetch_object();
			
			$text = "";
			$newMode = null;
			if(intval($user->mute) === 0){
				$newMode = 1;
				$text = "Выключил";
			}
			else if(intval($user->mute) === 1){
				$newMode = 0;
				$text = "Включил";
			}
			else
				throw new TelegramException($this->chat_id, "Упс, что-то не так с моей базой данных. Я сообщу об этом создателю.");
			
			
			$res = $this->sql->query("
				UPDATE `users`
				SET `mute` = $newMode
				WHERE `id` = {$this->getUserId()}
			");
			if($this->sql->affected_rows === 0)
				throw new TelegramException($this->chat_id, "Упс, что-то не так с моей базой данных. Я сообщу об этом создателю.");			
			
			$this->sendMessage(
				array(
					'text' => "$text все уведомления",
					'reply_markup' => array(
						'hide_keyboard' => true
					)
				)
			);
			break;
		
		case "/get_my_shows":
			$this->deletePreviousMessageArray();
			$res = $this->sql->query("
				SELECT CONCAT(
					`title_ru`,
					' (',
					`title_en`,
					')'
				) AS `title`
				FROM `tracks`
				JOIN `shows` ON `tracks`.`show_id` = `shows`.`id`
				WHERE `tracks`.`user_id` = {$this->getUserId()}
			");
			if($res->num_rows === 0){
				$this->sendMessage(
					array(
						'text' => "Вы не выбрали ни одного сериала",
						'reply_markup' => array(
							'hide_keyboard' => true
						)
					)
				);
			}
			else{
				$textByLines = array();
				array_push($textByLines, "Ваши сериалы:\n\n");
				while($show = $res->fetch_object()){
					array_push($textByLines, "• ".$show->title."\n\n");
				}
				
				$this->sendTextByLines(
					array(
						'chat_id' => $this->telegram_id,
						'reply_markup' => array(
							'hide_keyboard' => true
						)
					),
					$textByLines
				);
			}
			break;
			
		
		case "/add_show":
			$this->insertOrDeleteShow(true, $argc);
			break;
		
		case "/remove_show":
			$this->insertOrDeleteShow(false, $argc);
			break;
		default:
			$this->deletePreviousMessageArray();
			throw new TelegramException($this->chat_id, "Unknown command");
		}
		
		try{
			$this->sendToBotan($message, $messagesTextArray[0]);
		}
		catch(Exception $ex){
			//log error
		}
		
	}
}
		
		


