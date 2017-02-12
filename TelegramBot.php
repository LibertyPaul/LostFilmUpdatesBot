<?php
require_once(__DIR__.'/TelegramBot_base.php');
require_once(__DIR__.'/Exceptions/TelegramException.php');
require_once(__DIR__.'/Exceptions/StdoutTextException.php');
require_once(__DIR__.'/HTTPRequesterInterface.php');
require_once(__DIR__.'/config/config.php');
require_once(__DIR__.'/Notifier.php');

class TelegramBot extends TelegramBot_base{
	private $user_id;
	private $telegram_id;
	private $notifier;
	private $previousMessageArray;
	
	public function __construct($telegram_id, HTTPRequesterInterface $HTTPRequester, Notifier $notifier){
		TelegramBot_base::__construct($HTTPRequester);

		$user_id = null;
		
		assert(is_int($telegram_id));
		$this->telegram_id = $telegram_id;
		
		assert($notifier !== null);
		$this->notifier = $notifier;
	}	
	
	public function sendMessage($args){
		$args['chat_id'] = $this->telegram_id;

		try{
			return parent::sendMessage($args);
		}
		catch(TelegramException $tex){
			assert(false, 'parent::sendMessage has thrown TelegramException');
		}
	}

	private function getUserIdByTelegramId($telegram_id){
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
		
		$user = $isUserExistsQuery->fetch();
		if($user !== false){
			return $user['id'];
		}
		else{
			return null;
		}
	}

	private function getUserId(){
		if($this->user_id === null){
			$this->user_id = $this->getUserIdByTelegramId($this->telegram_id);
			if($this->user_id === null){// TODO: move error handling to another place
				$conversationStorage->deleteConversation();
				$this->tracer->log('[ERROR]', __FILE__, __LINE__, 'Unknown Telegram Id: '.$this->telegram_id);
				throw new TelegramException($this, 'Твой Telegram ID не найден в БД, ты регистрировался командой /start ?');
			}
		}
		return $this->user_id;
	}
	
	private function repeatQuestion(ConversationStorage $conversationStorage){
		$conversationStorage->deleteLastMessage();
	}
	
	private function isUserRegistred(){
		return $this->getUserIdByTelegramId($this->telegram_id) !== null;
	}
	
	private function registerUser(ConversationStorage $conversationStorage, $username, $firstName, $lastName){
		$conversationStorage->deleteConversation();
		
		if($this->isUserRegistred()){
			$this->sendMessage(
				array(
					'text' => 'Мы ведь уже знакомы, правда?'
				)
			);

			return;
		}
		
		$addUserQuery = $this->pdo->prepare('
			INSERT INTO `users` (
				`telegram_id`,
				`telegram_username`,
				`telegram_firstName`,
				`telegram_lastName`
			)
			VALUES (
				:telegram_id,
				:telegram_username,
				:telegram_firstname,
				:telegram_lastname
			)
		');
		
		try{
			$addUserQuery->execute(
				array(
					':telegram_id' 			=> $this->telegram_id,
					':telegram_username' 	=> $username,
					':telegram_firstname' 	=> $firstName,
					':telegram_lastname'	=> $lastName
				)
			);
		}
		catch(PDOException $ex){
			$this->botTracer->log('[DB ERROR]', __FILE__, __LINE__, $ex->getMessage());
			throw new TelegramException($this, 'По неизвестным причинам не могу тебя зарегистрировать. Напиши @libertypaul об этом, он разберется. Код TB'.__LINE__);
		}
		
		try{
			$this->notifier->newUserEvent($this->getUserId());
		}
		catch(Exception $ex){
			$this->botTracer->log('[NOTIFIER ERROR]', __FILE__, __LINE__, $ex->getMessage());
		}
		
		
		$welcomingText 	= "Привет, $username\n";
		$welcomingText .= "Я - бот LostFilm updates.\n";
		$welcomingText .= "Моя задача - оповестить тебя о выходе новых серий твоих любимых сериалов на сайте https://lostfilm.tv/\n\n";
		$welcomingText .= "Чтобы узнать что я умею - введи /help или выбери эту команду в списке";
		$this->sendMessage(
			array(
				'text' => $welcomingText,
				'disable_web_page_preview' => true,
			)
		);
	}
	
	private function unregisterUser(ConversationStorage $conversationStorage){
		$user_id = $this->getUserId();
		if($user_id === null){
			$conversationStorage->deleteConversation();
			$this->sendMessage(
				array(
					'text' => 
						'Ты еще не регистрировался, а уже пытаешься удалиться'.PHP_EOL.	
						'Не надо так...'
				)
			);

			return;
		}


		$ANSWER_YES = 'Да';
		$ANSWER_NO = 'Нет';
		$keyboard = array(
			array(
				$ANSWER_YES,
				$ANSWER_NO
			)
		);
		
		switch($conversationStorage->getConversationSize()){
		case 1:
			$this->sendMessage(
				array(
					'text' => 'Ты уверен? Вся информация о тебе будет безвозвратно потеряна...',
					'reply_markup' => array(
						'keyboard' => $keyboard,
						'one_time_keyboard' => false
					)
				)
			);
			
			break;
		
		case 2:
			$conversation = $conversationStorage->getConversation();
			$resp = $conversation[1];
			switch($resp){
			case $ANSWER_YES:
				$conversationStorage->deleteConversation();
				try{
					$this->notifier->userLeftEvent($this->getUserId());
				}
				catch(Exception $ex){
					$this->botTracer->logException('[NOTIFIER ERROR]', $ex);
				}
				
				$deleteUserQuery = $this->pdo->prepare('
					DELETE FROM `users`
					WHERE `id` = :user_id
				');
				
				try{
					$deleteUserQuery->execute(
						array(
							':user_id' => $this->getUserId()
						)
					);
				}
				catch(PDOException $ex){
					$this->botTracer->logException('[DB ERROR]', $ex);
					$conversationStorage->deleteConversation();
					throw new TelegramException($this, 'Возникла ошибка: Не получилось удалить тебя из контакт-листа');
				}
				
				$this->sendMessage(
					array(
						'text' => 'Прощай...',
						'reply_markup' => array(
							'hide_keyboard' => true
						)
					)
				);
				break;
				
			case $ANSWER_NO:
				$conversationStorage->deleteConversation();
				$this->sendMessage(
					array(
						'text' => 'Фух, а то я уже испугался',
						'reply_markup' => array(
							'hide_keyboard' => true
						)
					)
				);
				break;
			
			default:
				$this->repeatQuestion();
				$this->sendMessage(
					array(
						'text' => 'Что?'
					)
				);
			}
			break;
		}
	}
	
	private function showHelp(ConversationStorage $conversationStorage){
		$conversationStorage->deleteConversation();
		
		$helpText  = "LostFilm updates - бот, который оповещает о новых сериях на https://lostfilm.tv/\n\n";
		$helpText .= "Список команд:\n";
		$helpText .= "/add_show - Добавить уведомления о сериале\n";
		$helpText .= "/remove_show - Удалить уведомления о сериале\n";
		$helpText .= "/get_my_shows - Показать, на что ты подписан\n";
		$helpText .= "/mute - Выключить уведомления\n";
		$helpText .= "/cancel - Отменить команду\n";
		$helpText .= "/help - Показать это сообщение\n";
		$helpText .= "/stop - Удалиться из контакт-листа бота\n\n";

		$helpText .= "Telegram создателя: @libertypaul\n";
		$helpText .= "Ну и электропочта есть, куда ж без неё: admin@libertypaul.ru\n";
		$helpText .= "Исходники бота есть на GitHub: https://github.com/LibertyPaul/LostFilmUpdatesBot\n\n";
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
	}
	
	private function showUserShows(ConversationStorage $conversationStorage){
		$conversationStorage->deleteConversation();
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
		
		if(count($userShows) === 0){
			$this->sendMessage(
				array(
					'text'	=> 'Вы не выбрали ни одного сериала',
					'reply_markup' => array(
						'hide_keyboard' => true
					)
				)
			);

			return;
		}

		$textByLines = array();
		$textByLines[] = 'Ваши сериалы:';
		
		$showTemplate = '#ICON #TITLE';
		foreach($userShows as $show){
			$icon = '•';
			if($show['onAir'] === 'N'){
				$icon = '✕';
			}
			
			$textByLines[] = str_replace(
				array('#ICON', '#TITLE'),
				array($icon, $show['title']),
				$showTemplate
			);
		}
		
		$eol = PHP_EOL.PHP_EOL;
		
		$this->sendTextByLines(
			array(
				'chat_id' => $this->telegram_id,
				'reply_markup' => array(
					'hide_keyboard' => true
				)
			),
			$textByLines,
			$eol
		);
	}
	
	private function toggleMute(ConversationStorage $conversationStorage){
		$conversationStorage->deleteConversation();
		$isMutedQuery = $this->pdo->prepare('
			SELECT `mute`
			FROM `users`
			WHERE `id` = :user_id
		');
		
		try{
			$isMutedQuery->execute(
				array(
					':user_id' => $this->getUserId()
				)
			);
		}
		catch(PDOException $ex){
			$this->botTracer->logException('[DB ERROR]',$ex);
			throw new TelegramException($this, 'Ошибка БД, код TB:'.__LINE__);
		}
		
		$user = $isMutedQuery->fetch();
		if($user === false){
			$this->tracer->log('[ERROR]', __FILE__, __LINE__, 'User was not found '.$this->telegram_id);
			throw new TelegramException($this, 'Не могу найти тебя в списке пользователей.\nПопробуй выполнить команду /start и попробовать снова');
		}

		$action = null;
		$newMode = null;
		if($user['mute'] === 'N'){
			$newMode = 'Y';
			$action = 'Выключил';
		}
		else{
			assert($user['mute'] === 'Y');
			$newMode = 'N';
			$action = 'Включил';
		}
		
		$toggleMuteQuery = $this->pdo->prepare('
			UPDATE `users`
			SET `mute` = :mute
			WHERE `id` = :user_id
		');
		
		try{
			$toggleMuteQuery->execute(
				array(
					':mute' 	=> $newMode,
					':user_id'	=> $this->getUserId()
				)
			);
		}
		catch(PDOException $ex){
			$this->botTracer->logException('[DB ERROR]', $ex);
			$conversationStorage->deleteConversation();
			throw new TelegramException($this, 'Ошибка БД, код TB:'.__LINE__);
		}
		
		$this->sendMessage(
			array(
				'text' => "$action все уведомления",
				'reply_markup' => array(
					'hide_keyboard' => true
				)
			)
		);
	}
	
	private function insertOrDeleteShow($in_out_flag){//$in_out_flag
		$fullMessageArray = $this->getPreviousMessageArray();
		$argc = count($fullMessageArray);
		
		$successText = '';
		$action = null;
		if($in_out_flag){
			$successText = 'добавлен';
			$action = $this->pdo->prepare('
				INSERT INTO `tracks` (`user_id`, `show_id`) 
				VALUES (:user_id, :show_id)
			');
		}					
		else{
			$successText = 'удален';
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
					AND `shows`.`onAir` = 'Y'
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
			catch(PDOException $ex){
				$this->botTracer->logException('[DB ERROR]', $ex);
				$conversationStorage->deleteConversation();
				throw new TelegramException($this, 'Ошибка БД, код TB:'.__LINE__);
			}
			
			$showTitles = $query->fetchAll(PDO::FETCH_COLUMN, 'title');
			
			if(count($showTitles) === 0){
				$conversationStorage->deleteConversation();
				
				$reply = null;
				if($in_out_flag){
					$reply = 'Ты уже добавил все (!) сериалы из списка. И как ты успеваешь их смотреть??';
				}
				else{
					$reply = 'Нечего удалять';
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
			catch(PDOException $ex){
				$this->botTracer->logException('[DB ERROR]', $ex);
				$conversationStorage->deleteConversation();
				throw new TelegramException($this, 'Ошибка БД, код TB:'.__LINE__);
			}
				
			$res = $getShowId->fetchAll();
			if(count($res) > 0){//нашли совпадение по имени (пользователь нажал на кнопку или, что маловероятно, сам ввел точное название
				$conversationStorage->deleteConversation();
				
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
				catch(PDOException $ex){
					$this->botTracer->logException('[DB ERROR]', $ex);
					throw new TelegramException($this, 'Ошибка добавления в базу данных. Я сообщу об этом создателю.');
				}
				
				$this->sendMessage(
					array(
						'text' => "$title_all $successText",
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
						AND `shows`.`onAir` = 'Y'
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
				catch(PDOException $ex){
					$this->botTracer->logException('[DB ERROR]', $ex);
					$conversationStorage->deleteConversation();
					throw new TelegramException($this, 'Упс, возникла ошибка, код: TB'.__LINE__);
				}
				
				$res = $query->fetchAll();
				
				switch(count($res)){
				case 0://не найдено ни одного похожего названия
					$this->sendMessage(
						array(
							'text' => 'Не найдено подходящих названий',
							'reply_markup' => array(
								'hide_keyboard' => true
							)
						)
					);
					$conversationStorage->deleteConversation();
					break;
								
				case 1://найдено только одно подходящее название
					$conversationStorage->deleteConversation();
					$show = $res[0];
					try{
						$action->execute(
							array(
								':show_id' => $show['id'],
								':user_id' => $this->getUserId()
							)
						);
					}
					catch(PDOException $ex){
						$this->botTracer->logException('[DB ERROR]', $ex);
						throw new TelegramException($this, 'Ошибка записи');
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
							'text' => 'Какой из этих ты имел ввиду:',
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
			$conversationStorage->deleteConversation();
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
			catch(PDOException $ex){
				$this->botTracer->logException('[DB ERROR]', $ex);
				throw new TelegramException($this, 'Ошибка БД, код TB:'.__LINE__);
			}
			
			$res = $query->fetchAll();
			
			if(count($res) === 0){
				$this->sendMessage(
					array(
						'text' => 'Не могу найти такое название.',
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
				catch(PDOException $ex){
					$this->botTracer->logException('[DB ERROR]', $ex);
					throw new TelegramException($this, 'Ошибка БД, код TB:'.__LINE__);
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

	private function updateUserInfo($message){
		if(isset($message->from) === false){
			$this->tracer->log('[DATA ERROR]', __FILE__, __LINE__, 'Message from Telegram API has no "from" field: '.PHP_EOL.print_r($message, true));
			return;
		}

		$updateUserInfoQuery = $this->pdo->prepare('
			UPDATE `users`
			SET `telegram_username`		= :telegram_username,
				`telegram_firstName`	= :telegram_firstName,
				`telegram_lastName`		= :telegram_lastName
			WHERE `id` = :id
		');

		try{
			$updateUserInfoQuery->execute(
				array(
					'telegram_username'		=> $message->from->username,
					'telegram_firstName'	=> $message->from->first_name,
					'telegram_lastName'		=> $message->from->last_name,
					'id'					=> $this->getUserId()
				)
			);
		}
		catch(PDOException $ex){
			$this->tracer->logException('[DB ERROR]', $ex);
			$this->tracer->log('[DB ERROR]', __FILE__, __LINE__, PHP_EOL.print_r($message, true));
		}
	}
	
	public function incomingUpdate(ConversationStorage $conversationStorage){
		assert($conversationeStorage !== null);
		assert($message->from->id === $this->telegram_id);
		
		if($cmd === '/cancel'){
			$conversationStorage->deleteConversation();
		}
		
		if($this->previousMessageArraySize() === 0){
			$this->previousMessageArrayInsert($cmd);
		}
		else{
			$this->previousMessageArrayInsert($message->text);//добавляем сообщение к n предыдущих
		}
		$messagesTextArray = $this->getPreviousMessageArray();//забираем n + 1 сообщений
		
		$argc = count($messagesTextArray);
		
		if($argc < 1)
			throw new StdoutTextException('PreviousMessageArray is empty');
		switch($messagesTextArray[0]){
		case '/start':
			$this->registerUser(
				isset($message->from->username)		?	$message->from->username	: null,
				isset($message->from->first_name)	?	$message->from->first_name	: null,
				isset($message->from->last_name)	?	$message->from->last_name	: null
			);
			break;
		case '/cancel':
			$conversationStorage->deleteConversation();
			$this->sendMessage(
				array(
					'text' => 'Действие отменено.',
					'reply_markup' => array(
						'hide_keyboard' => true
					)
				)
			);
			break;
		case '/stop':
			$this->unregisterUser();
			break;
		
		case '/help':
			$this->showHelp();
			break;
		
		case '/mute':
			$this->toggleMute();
			break;
		
		case '/get_my_shows':
			$this->showUserShows();
			break;
			
		case '/add_show':
			$this->insertOrDeleteShow(true, $argc);
			break;
		
		case '/remove_show':
			$this->insertOrDeleteShow(false, $argc);
			break;
		default:
			$conversationStorage->deleteConversation();
			throw new TelegramException($this, 'Я хз чё это значит');
		}
		
	}
}
		
		


