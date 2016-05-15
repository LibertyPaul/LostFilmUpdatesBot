<?php
require_once(__DIR__."/TelegramBot_base.php");
require_once(__DIR__."/Exceptions/TelegramException.php");
require_once(__DIR__."/Exceptions/StdoutTextException.php");
require_once(__DIR__."/config/config.php");
require_once(__DIR__."/Botan/Botan.php");
require_once(__DIR__."/Notifier.php");

class TelegramBot extends TelegramBot_base{
	protected $telegram_id;//от кого пришло сообщение
	protected $chat_id;//куда отвечать(возможно не на прямую к пользователю, а в чат, где он написал боту)
	protected $botan;
	protected $notifier;
	
	public function __construct($telegram_id, $chat_id = null){
		if(is_int($telegram_id) === false){
			throw new StdoutTextException("incorrect telegram_id");
		}
		
		if($chat_id === null){
			$chat_id = $telegram_id;
		}
		else if(is_int($chat_id) === false){
			throw new StdoutTextException("incorrect chat_id");
		}
				
		TelegramBot_base::__construct();
		$this->telegram_id = $telegram_id;
		$this->chat_id = $chat_id;
		$this->botan = new Botan(BOTAN_API_KEY);
		$this->notifier = new Notifier();
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
	
	protected function getUserId(){
		static $user_id;
		if(isset($user_id) === false){
			$getUserIdQuery = $this->pdo->prepare('
				SELECT `id`
				FROM `users`
				WHERE `telegram_id` = :telegram_id
			');
			
			$getUserIdQuery->execute(
				array(
					':telegram_id' => $this->telegram_id
				)
			);
			
			$result = $getUserIdQuery->fetchAll();
			if(count($result) === 0){
				$this->deletePreviousMessageArray();			
				throw new TelegramException($this->chat_id, "Твой Telegram ID не найден в БД, ты регистрировался командой /start ?");
			}
				
			$user_id = intval($result[0]['id']);
		}
		return $user_id;
	}
	
	protected function updateUserInfo($telegram_username, $telegram_firstName){
		$updateUserInfoQuery = $this->pdo->prepare('
			UPDATE `users`
			SET 
				`telegram_username` 	= :username,
				`telegram_firstName` 	= :firstName
			WHERE `id` = :user_id
		');
		
		$updateUserInfoQuery->execute(
			array(
				':username' 	=> $telegram_username,
				':firstName'	=> $telegram_firstName,
				':user_id'		=> $this->getUserId()
			)
		);
		
		if($updateUserInfoQuery->rowCount() === 0){
			throw new StdoutTextException("updateUserInfo -> sql UPDATE error");
		}
	}
	
	protected function showUserShows(){
		$this->deletePreviousMessageArray();
		$getUserShowsQuery = $this->pdo->prepare("
			SELECT 
				CONCAT(
					`shows`.`title_ru`,
					' (',
					`shows`.`title_en`,
					')'
				) AS `title`,
				`shows`.`onAir`
			FROM `tracks`
			JOIN `shows` ON `tracks`.`show_id` = `shows`.`id`
			WHERE `tracks`.`user_id` = :user_id
			ORDER BY `shows`.`title_ru`
		");
		
		$getUserShowsQuery->execute(
			array(
				':user_id' => $this->getUserId()
			)
		);
		
		$userShows = $getUserShowsQuery->fetchAll();
		
		$textByLines = array();
		
		if(count($userShows) === 0){
			$textByLines[] = "Вы не выбрали ни одного сериала";
		}
		else{
			$textByLines[] = "Ваши сериалы:\n\n";
			
			$showTemplate = "#ICON #TITLE\n\n";
			foreach($userShows as $show){
				$icon = '•';
				if(intval($show['onAir']) === 0){
					$icon = '✕';
				}
				
				$textByLines[] = str_replace(
					array('#ICON', '#TITLE'),
					array($icon, $show['title']),
					$showTemplate
				);
			}
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
	
	protected function insertOrDeleteShow($in_out_flag){//$in_out_flag
		$fullMessageArray = $this->getPreviousMessageArray();
		$argc = count($fullMessageArray);
		
		$successText = "";
		$action = null;
		if($in_out_flag){
			$successText = "добавлен";
			$action = $this->pdo->prepare('
				INSERT INTO `tracks` (`user_id`, `show_id`) 
				VALUES (:user_id, :show_id)
			');
		}					
		else{
			$successText = "удален";
			$action = $this->pdo->prepare('
				DELETE FROM `tracks`
				WHERE `user_id` = :user_id
				AND   `show_id` = :show_id
			');
		}
	
	
		switch($argc){
		case 1:
			$query = null;
			if($in_out_flag){//true -> add_show, false -> remove show
				$query = $this->pdo->prepare("
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
						WHERE `tracks`.`user_id` = :user_id
					) AS `tracked`
					ON `shows`.`id` = `tracked`.`show_id`
					WHERE `tracked`.`id` IS NULL
					AND `shows`.`onAir` != 0
					ORDER BY `title`
				");
			}
			else{				
				$query = $this->pdo->prepare("
					SELECT
						CONCAT(
							`title_ru`,
							' (',
							`title_en`,
							')'
						) AS `title`
					FROM `tracks`
					JOIN `shows` ON `tracks`.`show_id` = `shows`.`id`
					WHERE `tracks`.`user_id` = :user_id
					ORDER BY `title`
				");
			}
			try{
				$query->execute(
					array(
						':user_id' => $this->getUserId()
					)
				);
			}
			catch(Exception $ex){
				echo __FILE__.':'.__LINE__."\t".$ex->getMessage();
				$this->deletePreviousMessageArray();
				throw new TelegramException($this->chat_id, 'Ошибка БД, код TB:'.__LINE__);
			}
			
			$showTitles = $query->fetchAll(PDO::FETCH_COLUMN, 'title');
			
			if(count($showTitles) === 0){
				$this->deletePreviousMessageArray();
				
				$reply = null;
				if($in_out_flag){
					$reply = "Ты уже добавил все (!) сериалы из списка. И как ты успеваешь их смотреть??";
				}
				else{
					$reply = "Нечего удалять";
				}
				
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
			$getShowId = $this->pdo->prepare("
				SELECT 
					`id`,
					CONCAT(
						`title_ru`,
						' (',
						`title_en`,
						')'
					) AS `title_all`
				FROM `shows`
				HAVING STRCMP(`title_all`, :title) = 0
			");
			
			try{
				$getShowId->execute(
					array(
						':title' => $fullMessageArray[1]
					)
				);
			}
			catch(Exception $ex){
				echo __FILE__.':'.__LINE__."\t".$ex->getMessage();
				$this->deletePreviousMessageArray();
				throw new TelegramException($this->chat_id, 'Ошибка БД, код TB:'.__LINE__);
			}
				
			$res = $getShowId->fetchAll();
			if(count($res) > 0){//нашли совпадение по имени (пользователь нажал на кнопку или, что маловероятно, сам ввел точное название
				$this->deletePreviousMessageArray();
				
				$show_id = intval($res[0]['id']);
				$title_all = $res[0]['title_all'];
				
				try{
					$action->execute(
						array(
							':show_id' => $show_id,
							':user_id' => $this->getUserId()
						)
					);
				}
				catch(Exception $ex){
					echo __FILE__.':'.__LINE__."\t".$ex->getMessage();
					throw new TelegramException($this->chat_id, "Ошибка добавления в базу данных. Я сообщу об этом создателю.");
				}
				
				$this->sendMessage(
					array(
						'text' => $title_all." $successText",
						'reply_markup' => array(
							'hide_keyboard' => true
						)
					)
				);
				
			}
			else{//совпадения не найдено. Скорее всего юзверь проебланил с названием. Придется угадывать.
				//TODO: если ненулевой результат у нескольких вариантов - дать пользователю выбрать
				$query = null;
				if($in_out_flag){//true -> add_show, false -> remove show
					$query = $this->pdo->prepare("
						SELECT
							`shows`.`id` AS `id`,
							MATCH(`title_ru`, `title_en`) AGAINST(:show_name) AS `score`,
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
							WHERE `tracks`.`user_id` = :user_id
						) AS `tracked`
						ON `shows`.`id` = `tracked`.`show_id`
						WHERE `tracked`.`id` IS NULL
						AND `shows`.`onAir` != 0
						HAVING `score` > 0.1
						ORDER BY `score` DESC
					");
				}
				else{				
					$query = $this->pdo->prepare("
						SELECT
							`shows`.`id` AS `id`,
							MATCH(`title_ru`, `title_en`) AGAINST(:show_name) AS `score`,
							CONCAT(
								`title_ru`,
								' (',
								`title_en`,
								')'
							) AS `title`
						FROM `tracks`
						JOIN `shows` ON `tracks`.`show_id` = `shows`.`id`
						WHERE `tracks`.`user_id` = :user_id
						HAVING `score` > 0.1
						ORDER BY `score` DESC
					");
				}
				
				try{
					$query->execute(
						array(
							':user_id' 		=> $this->getUserId(),
							':show_name'	=> $fullMessageArray[1]
						)
					);
				}
				catch(Exception $ex){
					echo __FILE__.':'.__LINE__."\t".$ex->getMessage();
					$this->deletePreviousMessageArray();
					throw new TelegramException($this->chat_id, 'Упс, возникла ошибка, код: TB'.__LINE__);
				}
				
				$res = $query->fetchAll();
				
				switch(count($res)){
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
					$this->deletePreviousMessageArray();
					$show = $res[0];
					try{
						$action->execute(
							array(
								':show_id' => $show['id'],
								':user_id' => $this->getUserId()
							)
						);
					}
					catch(Exception $ex){
						echo __FILE__.':'.__LINE__."\t".$ex->getMessage();
						throw new TelegramException($this->chat_id, "Ошибка записи");
					}
					
					$this->sendMessage(
						array(
							'text' => "$show[title] $successText",
							'reply_markup' => array(
								'hide_keyboard' => true
							)
						)
					);
					
					break;
				
				default://подходят несколько вариантов
					$showTitles = array();
					foreach($res as $predictedShow){
						$showTitles[] = $predictedShow['title'];
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
			$this->deletePreviousMessageArray();
			$query = $this->pdo->prepare("
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
				HAVING STRCMP(`title_all`, :exactShowName) = 0
			");
			
			try{
				$query->execute(
					array(
						':exactShowName' => $fullMessageArray[2]
					)
				);
			}
			catch(Exception $ex){
				echo __FILE__.':'.__LINE__."\t".$ex->getMessage();
				throw new TelegramException($this->chat_id, 'Ошибка БД, код TB:'.__LINE__);
			}
			
			$res = $query->fetchAll();
			
			if(count($res) === 0){
				$this->sendMessage(
					array(
						'text' => "Не могу найти такое название.",
						'reply_markup' => array(
							'hide_keyboard' => true
						)
					)
				);
			}
			else{
				$show = $res[0];
				try{
					$action->execute(
						array(
							':user_id' => $this->getUserId(),
							':show_id' => $show['id']
						)
					);
				}
				catch(Exception $ex){
					echo __FILE__.':'.__LINE__."\t".$ex->getMessage();
					throw new TelegramException($this->chat_id, 'Ошибка БД, код TB:'.__LINE__);
				}
				
				$this->sendMessage(
					array(
					'text' => "$show[title_ru] $successText",
						'reply_markup' => array(
							'hide_keyboard' => true
						)
					)
				);
			}
			
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
		if($message->from->id !== $this->telegram_id){
			throw new StdoutTextException("update telegram id, and stored id doesn't match");
		}
		
		if(isset($message->text) === false){
			throw new StdoutTextException('$message->text is empty');
		}
		
		$cmd = $this->extractCommand($message->text);
		
		if($cmd === "/cancel"){
			$this->deletePreviousMessageArray();
		}
		
		if($this->getArgc() === 0){
			$this->addPreviousMessageArray($cmd);
		}
		else{
			$this->addPreviousMessageArray($message->text);//добавляем сообщение к n предыдущих
		}
		$messagesTextArray = $this->getPreviousMessageArray();//забираем n + 1 сообщений
		
		print_r($messagesTextArray);
		$argc = count($messagesTextArray);
		
		if($argc < 1)
			throw new StdoutTextException("PreviousMessageArray is empty");
		switch($messagesTextArray[0]){
		case "/start":
			$this->deletePreviousMessageArray();
			if(isset($message->from->username) === false){
				$message->from->username = null;
			}
				
			if(isset($message->from->first_name) === false){
				$message->from->first_name = null;
			}
			
			if(isset($message->from->last_name) === false){
				$message->from->last_name = null;
			}
			
			$userId = null;
			try{
			
				$isUserExistsQuery = $this->pdo->prepare('
					SELECT `id`
					FROM `users`
					WHERE `telegram_id` = :telegram_id
				');
			
				$isUserExistsQuery->execute(
					array(
						':telegram_id' => $this->telegram_id
					)
				);
			
				if(empty($isUserExistsQuery->fetchAll()) === false){
					$this->sendMessage(
						array(
							'text' => "Мы ведь уже знакомы, правда?"
						)
					);
					break;
				}
			
				$addUserQuery = $this->pdo->prepare('
					INSERT INTO `users` (`telegram_id`, `telegram_username`, `telegram_firstName`, `telegram_lastName`)
					VALUES (:telegram_id, :telegram_username, :telegram_firstname, :telegram_lastname)
				');
			
				$addUserQuery->execute(
					array(
						':telegram_id' 			=> $this->telegram_id,
						':telegram_username' 	=> $message->from->username,
						':telegram_firstname' 	=> $message->from->first_name,
						':telegram_lastname'	=> $message->from->last_name
					)
				);
				
				
				try{
					$userId = $this->pdo->lastInsertId();
					$userId = intval($userId);
					$this->notifier->newUserEvent($userId);
				}
				catch(Exception $ex){
					// TODO: в лог файл
				}
			}
			catch(TelegramException $tex){
				throw $tex;
			}
			catch(Exception $ex){
				$this->sendMessage(
					array(
						'text' => "Упс, с регистрацией что-то пошло не так, напиши об этом пару строк создателю бота @libertypaul, если тебе не лень"
					)
				);
				
				throw $ex;
				break;
			}
			
			$startText 	= "Привет, ".$message->from->first_name."\n";
			$startText .= "Я - лостфильм бот, моя задача - оповестить тебя о выходе новых серий твоих любимых сериалов на сайте http://lostfilm.tv/\n\n";
			$startText .= "Чтобы узнать что я умею - введи /help или выбери эту команду в списке";
			$this->sendMessage(
				array(
					'text' => $startText,
					'disable_web_page_preview' => true,
				)
			);
			
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
					try{
						$this->notifier->userLeftEvent($this->getUserId());
					}
					catch(Exception $ex){
						// TODO: logging
					}
					
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
			$this->showUserShows();
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
		
		


