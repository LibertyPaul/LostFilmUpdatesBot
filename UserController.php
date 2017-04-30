<?php
require_once(__DIR__.'/config/config.php');
require_once(__DIR__.'/config/stuff.php');
require_once(__DIR__.'/NotificationGenerator.php');
require_once(__DIR__.'/ConversationStorage.php');
require_once(__DIR__.'/BotPDO.php');
require_once(__DIR__.'/Message.php');
require_once(__DIR__.'/MessageList.php');
require_once(__DIR__.'/Tracer.php');

class UserIsNotRegistredException extends OutOfBoundsException{}

class UserController{
	private $user_id;
	private $telegram_id;

	private $pdo;
	private $memcache;

	private $tracer;

	public function __construct($telegram_id){
		$this->pdo = BotPDO::getInstance();
		$this->memcache = createMemcache();

		$user_id = null;
		
		assert(is_int($telegram_id));
		$this->telegram_id = $telegram_id;

		$this->tracer = new Tracer(__CLASS__);
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
				$this->tracer->logError('[ERROR]', __FILE__, __LINE__, 'Unknown Telegram Id: '.$this->telegram_id);
				throw new UserIsNotRegistredException();
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
			return new Message($this->telegram_id, 'Мы ведь уже знакомы, правда?');
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
			$this->tracer->logException('[DB ERROR]', __FILE__, __LINE__, $ex);
			return new Message($this->telegram_id, 'Возникла ошибка. @libertypaul уже в курсе и скоро починит.');
		}
		
		$messageList = new MessageList();
		try{
			$notificationGenerator = new NotificationGenerator();
			$notification = $notificationGenerator->newUserEvent($this->getUserId());
			$messageList->add($notification);
		}
		catch(UserIsNotRegistredException $ex){
			$this->tracer->logWarning('[USER]', __FILE__, __LINE__, 'Attempt to use bot without registration');
			return new Message($this->telegram_id, 'Твой Telegram ID не найден в БД, ты регистрировался командой /start ?');
		}
		catch(Exception $ex){
			$this->tracer->logError('[NOTIFIER ERROR]', __FILE__, __LINE__, $ex->getMessage());
		}
		
		
		$welcomingText 	= "Привет, $username\n";
		$welcomingText .= "Я - бот LostFilm updates.\n";
		$welcomingText .= "Моя задача - оповестить тебя о выходе новых серий твоих любимых сериалов на сайте https://lostfilm.tv/\n\n";
		$welcomingText .= "Чтобы узнать что я умею - введи /help или выбери эту команду в списке";

		$messageList->add(new Message($this->telegram_id, $welcomingText, null, true));

		return $messageList;
	}

	private function cancelRequest(ConversationStorage $conversationStorage){
		$conversationStorage->deleteConversation();
	
		return new Message(
			$this->telegram_id,
			'Действие отменено.',
			null,
			null,
			null,
			null,
			array(
				'hide_keyboard' => true
			)
		);
	}
	
	private function unregisterUser(ConversationStorage $conversationStorage){
		try{
			$user_id = $this->getUserId();
		}
		catch(UserIsNotRegistredException $ex){
			$conversationStorage->deleteConversation();

			$text = 'Ты еще не регистрировался, а уже пытаешься удалиться'.PHP_EOL.	
					'Не надо так...';

			return new Message($this->telegram_id, $text);
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
			return new Message(
				$this->telegram_id,
				'Ты уверен? Вся информация о тебе будет безвозвратно потеряна...',
				null,
				null,
				null,
				null,
				array(
					'keyboard' => $keyboard,
					'one_time_keyboard' => false
				)
			);
	
			break;
		
		case 2:
			$conversation = $conversationStorage->getConversation();
			$resp = $conversation[1];
			switch($resp){
			case $ANSWER_YES:
				$conversationStorage->deleteConversation();

				$messageList = new MessageList();
				
				try{
					$notificationGenerator = new NotificationGenerator();
					$notification = $notificationGenerator->userLeftEvent($user_id);
					$messageList->add($notification);
				}
				catch(Exception $ex){
					$this->tracer->logException('[NOTIFIER ERROR]', __FILE__, __LINE__, $ex);
				}
				
				$deleteUserQuery = $this->pdo->prepare('
					DELETE FROM `users`
					WHERE `id` = :user_id
				');
				
				try{
					$deleteUserQuery->execute(
						array(
							':user_id' => $user_id
						)
					);
				}
				catch(PDOException $ex){
					$this->tracer->logException('[DB ERROR]', __FILE__, __LINE__, $ex);
					$conversationStorage->deleteConversation();
					return new Message($this->telegram_id, 'Возникла ошибка. Записал, починят.');
				}
				
				$response = new Message(
					$this->telegram_id,
					'Прощай...',
					null,
					null,
					null,
					null,
					array(
						'hide_keyboard' => true
					)
				);

				$messageList->add($response);
				
				return $messageList;
				break;
				
			case $ANSWER_NO:
				$conversationStorage->deleteConversation();
				
				return new Message(
					$this->telegram_id,
					'Фух, а то я уже испугался',
					null,
					null,
					null,
					null,
					array(
						'hide_keyboard' => true
					)
				);
					
				break;
			
			default:
				$this->repeatQuestion($conversationStorage);
				return new Message($this->telegram_id, 'Что?');
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
		
		return new Message(
			$this->telegram_id,
			$helpText,
			null,
			true,
			null,
			null,
			array(
				'hide_keyboard' => true
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

		try{
			$user_id = $this->getUserId();
		}
		catch(UserIsNotRegistredException $ex){
			return new Message($this->telegram_id, 'Сначала надо зарегистрироваться командой /start');
		}
		
		$getUserShowsQuery->execute(
			array(
				':user_id' => $user_id
			)
		);
		
		$userShows = $getUserShowsQuery->fetchAll();
		
		if(count($userShows) === 0){
			return new Message(
				$this->telegram_id,
				'Вы не выбрали ни одного сериала',
				null,
				null,
				null,
				null,
				array(
					'hide_keyboard' => true
				)
			);
		}

		$text = 'Ваши сериалы:';
		
		foreach($userShows as $show){
			$icon = '•';
			if($show['onAir'] === 'N'){
				$icon = '✕';
			}
			
			$text .= str_replace(
				array('#ICON', '#TITLE'),
				array($icon, $show['title']),
				PHP_EOL.PHP_EOL.'#ICON #TITLE'
			);
		}
		
		return new Message(
			$this->telegram_id,
			$text,
			null,
			null,
			null,
			null,
			array(
				'hide_keyboard' => true
			)
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
			$user_id = $this->getUserId();
		}
		catch(UserIsNotRegistredException $ex){
			return new Message($this->telegram_id, 'Сначала надо зарегистрироваться командой /start');
		}
		
		try{
			$isMutedQuery->execute(
				array(
					':user_id' => $user_id
				)
			);
		}
		catch(PDOException $ex){
			$this->tracer->logException('[DB ERROR]', __FILE__, __LINE__,$ex);
			return new Message($this->telegram_id, 'Возникла ошибка в базе. Записал. Починят.');
		}
		
		$user = $isMutedQuery->fetch();
		if($user === false){
			$this->tracer->logError('[ERROR]', __FILE__, __LINE__, 'User was not found '.$this->telegram_id);
			return new Message($this->telegram_id, 'Не могу найти тебя в списке пользователей.\nПопробуй выполнить команду /start');
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
					':user_id'	=> $user_id
				)
			);
		}
		catch(PDOException $ex){
			$this->tracer->logException('[DB ERROR]', __FILE__, __LINE__, $ex);
			return new Message($this->telegram_id, 'Возникла ошибка в базе. Записал. Починят.');
		}
		
		return new Message(
			$this->telegram_id,
			"$action все уведомления",
			null,
			null,
			null,
			null,
			array(
				'hide_keyboard' => true
			)
		);
	}
	
	private function insertOrDeleteShow(ConversationStorage $conversationStorage, $in_out_flag){
		$conversation = $conversationStorage->getConversation();
		
		try{
			$user_id = $this->getUserId();
		}
		catch(UserIsNotRegistredException $ex){
			$conversationStorage->deleteConversation();
			return new Message($this->telegram_id, 'Сначала надо зарегистрироваться командой /start');
		}
		
		
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
	
	
		switch($conversationStorage->getConversationSize()){
		case 1:
			$query = $this->pdo->prepare("
				SELECT
					CONCAT(
						`title_ru`,
						' (',
						`title_en`,
						')'
					) AS `title`
				FROM `shows`
				WHERE (
					`id` IN(
						SELECT `show_id`
						FROM `tracks`
						WHERE `user_id` = :user_id
					)
					XOR :in_out_flag
				)
				AND ((`shows`.`onAir` = 'Y') OR NOT :in_out_flag)
			");

			try{
				$query->execute(
					array(
						':user_id'		=> $user_id,
						':in_out_flag'	=> $in_out_flag
					)
				);
			}
			catch(PDOException $ex){
				$this->tracer->logException('[DB ERROR]', __FILE__, __LINE__, $ex);
				$conversationStorage->deleteConversation();
				return new Message($this->telegram_id, 'Ошибка в базе. Записал. Починят.'); 
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
				
				return new Message(
					$this->telegram_id,
					$reply,
					null,
					null,
					null,
					null,
					array(
						'hide_keyboard' => true
					)
				);
			}
			else{
				$keyboard = $this->createKeyboard($showTitles);

				$text = 'Как называется сериал?'.PHP_EOL.
						'Выбери из списка или введи пару слов из названия.';

				return new Message(
					$this->telegram_id,
					$text,
					null,
					null,
					null,
					null,
					array(
						'keyboard' => $keyboard,
						'one_time_keyboard' => true
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
				WHERE ((`shows`.`onAir` = 'Y') OR NOT :in_out_flag)
				AND (
					`id` IN(
						SELECT `show_id`
						FROM `tracks`
						WHERE `user_id` = :user_id
					)
					XOR :in_out_flag
				)
				HAVING `title_all` = :title
			");
			
			try{
				$getShowId->execute(
					array(
						':title'		=> $conversation[1],
						':user_id'		=> $user_id,
						':in_out_flag'	=> $in_out_flag
					)
				);
			}
			catch(PDOException $ex){
				$this->tracer->logException('[DB ERROR]', __FILE__, __LINE__, $ex);
				$conversationStorage->deleteConversation();
				return new Message($this->telegram_id, 'Ошибка в базе. Записал. Починят.'); 
			}
				
			$res = $getShowId->fetch();
			if($res !== false){//нашли совпадение по имени (пользователь нажал на кнопку или, что маловероятно, сам ввел точное название
				$conversationStorage->deleteConversation();
				
				$show_id = intval($res['id']);
				$title_all = $res['title_all'];
				
				try{
					$action->execute(
						array(
							':show_id' => $show_id,
							':user_id' => $user_id
						)
					);
				}
				catch(PDOException $ex){
					$this->tracer->logException('[DB ERROR]', __FILE__, __LINE__, $ex);
					return new Message($this->telegram_id, 'Ошибка в базе. Записал. Починят.'); 
				}
				
				return new Message(
					$this->telegram_id,
					"$title_all $successText",
					null,
					null,
					null,
					null,
					array(
						'hide_keyboard' => true
					)
				);
				
			}
			else{//совпадения не найдено. Скорее всего юзверь проебланил с названием. Придется угадывать.
				$query = $this->pdo->prepare("
					SELECT
						`id`,
						MATCH(`title_ru`, `title_en`) AGAINST(:show_name) AS `score`,
						CONCAT(
							`title_ru`,
							' (',
							`title_en`,
							')'
						) AS `title_all`
					FROM `shows`
					WHERE (
						`id` IN(
							SELECT `show_id`
							FROM `tracks`
							WHERE `user_id` = :user_id
						)
						XOR :in_out_flag
					)
					AND ((`shows`.`onAir` = 'Y') OR NOT :in_out_flag)
					HAVING `score` > 0.1
					ORDER BY `score` DESC
				");
				
				try{
					$query->execute(
						array(
							':user_id' 		=> $user_id,
							':show_name'	=> $conversation[1],
							':in_out_flag'	=> $in_out_flag
						)
					);
				}
				catch(PDOException $ex){
					$this->tracer->logException('[DB ERROR]', __FILE__, __LINE__, $ex);
					$conversationStorage->deleteConversation();
					return new Message($this->telegram_id, 'Ошибка в базе. Записал. Починят.'); 
				}
				
				$res = $query->fetchAll();
				
				switch(count($res)){
				case 0://не найдено ни одного похожего названия
					$conversationStorage->deleteConversation();
					return new Message(
						$this->telegram_id,
						'Не найдено подходящих названий',
						null,
						null,
						null,
						null,
						array(
							'hide_keyboard' => true
						)
					);
					break;
								
				case 1://найдено только одно подходящее название
					$conversationStorage->deleteConversation();
					$show = $res[0];
					try{
						$action->execute(
							array(
								':show_id' => $show['id'],
								':user_id' => $user_id
							)
						);
					}
					catch(PDOException $ex){
						$this->tracer->logException('[DB ERROR]', __FILE__, __LINE__, $ex);
						return new Message($this->telegram_id, 'Ошибка в базе. Записал. Починят.'); 
					}
					
					return new Message(
						$this->telegram_id,
						"$show[title_all] $successText",
						null,
						null,
						null,
						null,
						array(
							'hide_keyboard' => true
						)
					);
					
					break;
				
				default://подходят несколько вариантов
					$showTitles = array();
					foreach($res as $predictedShow){
						$showTitles[] = $predictedShow['title_all'];
					}
					$keyboard = $this->createKeyboard($showTitles);
				
					return new Message(
						$this->telegram_id,
						'Какой из этих ты имел ввиду:',
						null,
						null,
						null,
						null,
						array(
							'keyboard' => $keyboard,
							'one_time_keyboard' => true
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
					) AS `title_all`
				FROM `shows`
				WHERE ((`shows`.`onAir` = 'Y') OR NOT :in_out_flag)
				AND (
					`id` IN(
						SELECT `show_id`
						FROM `tracks`
						WHERE `user_id` = :user_id
					)
					XOR :in_out_flag
				)
				HAVING `title_all` = :exactShowName
			");
			
			try{
				$query->execute(
					array(
						':user_id' 		=> $user_id,
						':in_out_flag'	=> $in_out_flag,
						':exactShowName' => $conversation[2]
					)
				);
			}
			catch(PDOException $ex){
				$this->tracer->logException('[DB ERROR]', __FILE__, __LINE__, $ex);
				return new Message($this->telegram_id, 'Ошибка в базе. Записал. Починят.'); 
			}
			
			$res = $query->fetchAll();
			
			if(count($res) === 0){
				return new Message(
					$this->telegram_id,
					'Не могу найти такое название.',
					null,
					null,
					null,
					null,
					array(
						'hide_keyboard' => true
					)
				);
			}
			else{
				$show = $res[0];
				try{
					$action->execute(
						array(
							':user_id' => $user_id,
							':show_id' => $show['id']
						)
					);
				}
				catch(PDOException $ex){
					$this->tracer->logException('[DB ERROR]', __FILE__, __LINE__, $ex);
					return new Message($this->telegram_id, 'Ошибка в базе. Записал. Починят.'); 
				}
				
				return new Message(
					$this->telegram_id,
					"$show[title_all] $successText",
					null,
					null,
					null,
					null,
					array(
						'hide_keyboard' => true
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

	public function createKeyboard($items){
		$rowSize = 2;
		$keyboard = array();
		$currentRow = array("/cancel");
		$currentRowPos = 1;
		foreach($items as $item){
			$currentRow[] = $item;
			if(++$currentRowPos % $rowSize == 0){
				$keyboard[] = $currentRow;
				$currentRow = array();
			}
		}
		if(count($currentRow) !== 0)
			$keyboard[] = $currentRow;
		return $keyboard;
	}

	private function updateUserInfo($message){
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
		catch(UserIsNotRegistredException $ex){
			$this->tracer->logWarning('[USER]', __FILE__, __LINE__, 'updateUserInfo on unregistred user');
			return;
		}
		catch(PDOException $ex){
			$this->tracer->logException('[DB ERROR]', __FILE__, __LINE__, $ex);
			$this->tracer->logError('[DB ERROR]', __FILE__, __LINE__, PHP_EOL.print_r($message, true));
		}
	}
	
	public function incomingUpdate(ConversationStorage $conversationStorage, $userInfo){
		assert($conversationStorage !== null);
		assert($conversationStorage->getConversationSize() > 0);

		if($conversationStorage->getLastMessage() === '/cancel'){
			return $this->cancelRequest($conversationStorage);
		}

		$response = null;
		
		try{
			switch($conversationStorage->getFirstMessage()){
			case '/start':
				$response = $this->registerUser(
					$conversationStorage,
					$userInfo['username'],
					$userInfo['first_name'],
					$userInfo['last_name']
				);
				break;

			case '/cancel':
				$response = $this->cancelRequest($conversationStorage);
				break;

			case '/stop':
				$response = $this->unregisterUser($conversationStorage);
				break;
			
			case '/help':
				$response = $this->showHelp($conversationStorage);
				break;
			
			case '/mute':
				$response = $this->toggleMute($conversationStorage);
				break;
			
			case '/get_my_shows':
				$response = $this->showUserShows($conversationStorage);
				break;
				
			case '/add_show':
				$response = $this->insertOrDeleteShow($conversationStorage, true);
				break;
			
			case '/remove_show':
				$response = $this->insertOrDeleteShow($conversationStorage, false);
				break;

			default:
				$conversationStorage->deleteConversation();
				$response = new Message($this->telegram_id, 'Я хз чё это значит'); 
			}
		}
		catch(Exception $ex){
			$response = new Message(
				$this->telegram_id,
				'Произошла ошибка, я сообщу об этом создателю.'
			);
			$this->tracer->logException('[BOT]', __FILE__, __LINE__, $ex);
		}
		
		return $response;
	}
}
		
		


