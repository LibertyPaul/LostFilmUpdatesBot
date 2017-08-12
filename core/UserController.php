<?php

namespace core;

require_once(__DIR__.'/NotificationGenerator.php');
require_once(__DIR__.'/ConversationStorage.php');
require_once(__DIR__.'/BotPDO.php');
require_once(__DIR__.'/OutgoingMessage.php');
require_once(__DIR__.'/../lib/Tracer/Tracer.php');

class UserController{
	private $user_id; # TODO: load full users record
	private $conversationStorage;
	private $messageDestination;
	private $pdo;
	private $tracer;

	public function __construct($user_id, ConversationStorage $conversationStorage){
		$this->tracer = new \Tracer(__CLASS__);
		$this->pdo = \BotPDO::getInstance();

		if(is_int($user_id) === false){
			$this->tracer->logError(
				'[INVALID TYPE]', __FILE__, __LINE__,
				"Invalid user_id type ($user_id)"
			);

			throw new \InvalidArgumentException("Invalid user_id type ($user_id)");
		}

		$this->user_id = $user_id;

		if($conversationStorage === null){
			$this->tracer->logError(
				'[INVALID ARGUMENT]', __FILE__, __LINE__,
				'Provided ConversationStorage is null'
			);

			throw new \InvalidArgumentException('Provided ConversationStorage is null');
		}

		$this->conversationStorage = $conversationStorage;

	}

	private function repeatQuestion(){
		$this->conversationStorage->deleteLastMessage();
	}
	
	private function welcomeUser(){
		$this->conversationStorage->deleteConversation();

		$getMessagesHistorySize = $this->pdo->prepare('
			SELECT  COUNT(*) FROM `messagesHistory`
			WHERE `user_id` = :user_id
		');

		try{
			$getMessagesHistorySize->execute(
				array(
					':user_id' => $this->user_id
				)
			);

			$res = $getMessagesHistorySize->fetch();
			$count = intval($res[0]);

			if($count > 1){
				return new DirectedOutgoingMessage(
					$this->user_id,
					new OutgoingMessage(
						'Мы ведь уже знакомы, правда?'
					)
				);
			}
		}
		catch(\PDOException $ex){
			$this->tracer->logException('[DB]', __FILE__, __LINE__, $ex);
			$username = '';
		}

		$welcomingText =
			'Привет, %username%'.PHP_EOL.	#TODO: pass real username
			'Я - бот LostFilm updates.'.PHP_EOL.
			'Моя задача - оповестить тебя о выходе новых серий '.
			'твоих любимых сериалов на сайте https://lostfilm.tv/'.PHP_EOL.PHP_EOL.
			'Чтобы узнать что я умею - введи /help или выбери эту команду в списке';
		
		$response = new DirectedOutgoingMessage(
			$this->user_id,
			new OutgoingMessage($welcomingText)
		);

		try{
			$notificationGenerator = new NotificationGenerator();
			$adminNotification = $notificationGenerator->newUserEvent($this->user_id);
			$response->appendMessage($adminNotification);
		}
		catch(\Exception $ex){
			$this->tracer->logException('[NOTIFIER ERROR]', __FILE__, __LINE__, $ex);
		}

		return $response;
	}

	private function cancelRequest(){
		$this->conversationStorage->deleteConversation();
		return new DirectedOutgoingMessage(
			$this->user_id,
			new OutgoingMessage('Действие отменено.')
		);
	}

	private function deleteUser(){
		$ANSWER_YES = 'Да';
		$ANSWER_NO = 'Нет';
		
		switch($this->conversationStorage->getConversationSize()){
		case 1:
			return new DirectedOutgoingMessage(
				$this->user_id,
				new OutgoingMessage(
					'Ты уверен? Вся информация о тебе будет безвозвратно потеряна...',
					false,
					false,
					array($ANSWER_YES, $ANSWER_NO)
				)
			);
	
			break;
		
		case 2:
			switch($this->conversationStorage->getLastMessage()){
			case $ANSWER_YES:
				$this->conversationStorage->deleteConversation();

				$response = null;
				
				try{
					$notificationGenerator = new NotificationGenerator();
					$response = $notificationGenerator->userLeftEvent($this->user_id);
				}
				catch(\Exception $ex){
					$this->tracer->logException('[NOTIFIER ERROR]', __FILE__, __LINE__, $ex);
				}
				
				$deleteUserQuery = $this->pdo->prepare("
					UPDATE `users`
					SET `deleted` = 'Y'
					WHERE `id` = :user_id
				");
				
				try{
					$deleteUserQuery->execute(
						array(
							':user_id' => $this->user_id
						)
					);
				}
				catch(\PDOException $ex){
					$this->tracer->logException('[DB ERROR]', __FILE__, __LINE__, $ex);
					$this->conversationStorage->deleteConversation();
					return new DirectedOutgoingMessage(
						$this->user_id,
						new OutgoingMessage('Возникла ошибка. Записал, починят.')
					);
				}
				
				$response->appendMessage(
					new DirectedOutgoingMessage(
						$this->user_id,
						new OutgoingMessage('Прощай...')
					)
				);

				return $response;
				
			case $ANSWER_NO:
				$this->conversationStorage->deleteConversation();
				return new DirectedOutgoingMessage(
					$this->user_id,
					new OutgoingMessage('Фух, а то я уже испугался')
				);
			
			default:
				$this->repeatQuestion($conversationStorage);
				return new DirectedOutgoingMessage(
					$this->user_id,
					new OutgoingMessage(
						'Давай конкретнее, либо да, либо нет',
						false,
						false,
						array($ANSWER_YES, $ANSWER_NO)
					)
				);
			}
			break;

		default:
			$this->tracer->logError(
				'[USER CONTROLLER]', __FILE__, __LINE__,
				'3rd message in /stop conversation'.PHP_EOL.
				print_r($this->conversationStorage->getConversation(), true)
			);
			$this->conversationStorage->deleteConversation();
		}
	}
	
	private function showHelp(){
		$this->conversationStorage->deleteConversation();
		
		$helpText =
			'LostFilm updates - бот, который оповещает '								.
			'о новых сериях на https://lostfilm.tv/'							.PHP_EOL
																				.PHP_EOL.
			'Список команд:'													.PHP_EOL.
			'/add_show - Добавить уведомления о сериале'						.PHP_EOL.
			'/remove_show - Удалить уведомления о сериале'						.PHP_EOL.
			'/get_my_shows - Показать, на что ты подписан'						.PHP_EOL.
			'/mute - Выключить уведомления'										.PHP_EOL.
			'/cancel - Отменить командa'										.PHP_EOL.
			'/help - Показать это сообщение'									.PHP_EOL.
			'/stop - Удалиться из контакт-листа бота'							.PHP_EOL
																				.PHP_EOL.
			'Telegram создателя: @libertypaul'									.PHP_EOL.
			'Ну и электропочта есть, куда ж без неё: admin@libertypaul.ru'		.PHP_EOL.
			'Исходники бота есть на GitHub: '											.
			'https://github.com/LibertyPaul/LostFilmUpdatesBot'					.PHP_EOL
																				.PHP_EOL.
			'Создатель бота не имеет никакого отношеня к проекту lostfilm.tv.';
		
		return new DirectedOutgoingMessage(
			$this->user_id,
			new OutgoingMessage($helpText)
		);
	}
	
	private function showUserShows(){
		$this->conversationStorage->deleteConversation();
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
				':user_id' => $this->user_id
			)
		);
		
		$userShows = $getUserShowsQuery->fetchAll();
		
		if(count($userShows) === 0){
			return new DirectedOutgoingMessage(
				$this->user_id,
				new OutgoingMessage('Вы пока не выбрали ни одного сериала')
			);
		}

		$parts = array();

		$text = 'Ваши сериалы:';
		
		foreach($userShows as $show){
			$icon = '•';
			if($show['onAir'] === 'N'){
				$icon = '✕';
			}

			$showRow = str_replace(
				array('#ICON', '#TITLE'),
				array($icon, $show['title']),
				PHP_EOL.PHP_EOL.'#ICON #TITLE'
			);

			if(strlen($text) + strlen($showRow) > 4000){
				$parts[] = $text;
				$text = '';
			}

			$text .= $showRow;
		}

		$parts[] = $text;

		$outgoingMessage = new OutgoingMessage($parts[0]);
		for($i = 1; $i < count($parts); ++$i){
			$nextMessage = new OutgoingMessage($parts[$i]);
			$outgoingMessage->appendMessage($nextMessage);
		}

		return new DirectedOutgoingMessage($this->user_id, $outgoingMessage);
	}
	
	private function toggleMute(){
		$this->conversationStorage->deleteConversation();
		$isMutedQuery = $this->pdo->prepare('
			SELECT `mute`
			FROM `users`
			WHERE `id` = :user_id
		');

		try{
			$isMutedQuery->execute(
				array(
					':user_id' => $this->user_id
				)
			);
		}
		catch(\PDOException $ex){
			$this->tracer->logException('[DB ERROR]', __FILE__, __LINE__,$ex);
			return new DirectedOutgoingMessage(
				$this->user_id,
				new OutgoingMessage('Возникла ошибка в базе. Записал. Починят.')
			);
		}
		
		$res = $isMutedQuery->fetch();
		if($res === false){
			$this->tracer->logError(
				'[ERROR]', __FILE__, __LINE__,
				'User was not found '.$this->user_id
			);

			return new DirectedOutgoingMessage(
				$this->user_id,
				new OutgoingMessage(
					'Не могу найти тебя в списке пользователей.'.PHP_EOL.
					'Попробуй выполнить команду /start'
				)
			);
		}

		$action = null;
		$newMode = null;
		if($res['mute'] === 'N'){
			$newMode = 'Y';
			$action = 'Выключил';
		}
		else{
			assert($res['mute'] === 'Y');
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
					':user_id'	=> $this->user_id
				)
			);
		}
		catch(\PDOException $ex){
			$this->tracer->logException('[DB ERROR]', __FILE__, __LINE__, $ex);
			return new DirectedOutgoingMessage(
				$this->user_id,
				new OutgoingMessage('Возникла ошибка в базе. Записал. Починят.')
			);
		}
		
		return new DirectedOutgoingMessage(
			$this->user_id,
			new OutgoingMessage("$action все уведомления")
		);
	}
	
	private function insertOrDeleteShow($in_out_flag){
		$conversation = $this->conversationStorage->getConversation();
		
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
	
	
		switch($this->conversationStorage->getConversationSize()){
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
				ORDER BY `title_ru`, `title_en`
			");

			try{
				$query->execute(
					array(
						':user_id'		=> $this->user_id,
						':in_out_flag'	=> $in_out_flag
					)
				);
			}
			catch(\PDOException $ex){
				$this->tracer->logException('[DB ERROR]', __FILE__, __LINE__, $ex);
				$this->conversationStorage->deleteConversation();
				return new DirectedOutgoingMessage(
					$this->user_id,
					new OutgoingMessage('Ошибка в базе. Записал. Починят.')
				); 
			}
			
			$showTitles = $query->fetchAll(\PDO::FETCH_COLUMN, 'title');
			
			if(count($showTitles) === 0){
				$this->conversationStorage->deleteConversation();
				
				$text = null;
				if($in_out_flag){
					$text =
						'Ты уже добавил все (!) сериалы из списка.'.PHP_EOL.
						'И как ты успеваешь их смотреть??';
				}
				else{
					$text = 'Нечего удалять';
				}
				
				return new DirectedOutgoingMessage(
					$this->user_id,
					new OutgoingMessage($text)
				);
			}
			else{
				$text = 'Как называется сериал?'.PHP_EOL.
						'Выбери из списка или введи пару слов из названия.';
				
				array_unshift($showTitles, '/cancel');
				array_push($showTitles, '/cancel');
				
				return new DirectedOutgoingMessage(
					$this->user_id,
					new OutgoingMessage(
						$text,
						false,
						false,
						$showTitles
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
				ORDER BY `title_ru`, `title_en`
			");
			
			try{
				$getShowId->execute(
					array(
						':title'		=> $this->conversationStorage->getLastMessage(),
						':user_id'		=> $this->user_id,
						':in_out_flag'	=> $in_out_flag
					)
				);
			}
			catch(\PDOException $ex){
				$this->tracer->logException('[DB ERROR]', __FILE__, __LINE__, $ex);
				$this->conversationStorage->deleteConversation();
				return new DirectedOutgoingMessage(
					$this->user_id,
					new OutgoingMessage('Ошибка в базе. Записал. Починят.')
				); 
			}
				
			$res = $getShowId->fetch();
			if($res !== false){
				# нашли совпадение по имени (пользователь нажал на кнопку или, что маловероятно, 
				# сам ввел точное название)
				$this->conversationStorage->deleteConversation();
				
				$show_id = intval($res['id']);
				$title_all = $res['title_all'];
				
				try{
					$action->execute(
						array(
							':show_id' => $show_id,
							':user_id' => $this->user_id
						)
					);
				}
				catch(\PDOException $ex){
					$this->tracer->logException('[DB ERROR]', __FILE__, __LINE__, $ex);
					return new DirectedOutgoingMessage(
						$this->user_id,
						new OutgoingMessage('Ошибка в базе. Записал. Починят.')
					); 
				}
				
				return new DirectedOutgoingMessage(
					$this->user_id,
					new OutgoingMessage("$title_all $successText")
				);
			}
			else{
				# Совпадения не найдено. Скорее всего юзверь проебланил с названием.
				# Придется угадывать.
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
				"); #TODO: alter in_out_flag from bool to string ('Add', 'Remove')
				
				try{
					$query->execute(
						array(
							':user_id' 		=> $this->user_id,
							':show_name'	=> $conversation[1],
							':in_out_flag'	=> $in_out_flag
						)
					);
				}
				catch(\PDOException $ex){
					$this->tracer->logException('[DB ERROR]', __FILE__, __LINE__, $ex);
					$this->conversationStorage->deleteConversation();
					return new DirectedOutgoingMessage(
						$this->user_id,
						new OutgoingMessage('Ошибка в базе. Записал. Починят.')
					); 
				}
				
				$res = $query->fetchAll();
				
				switch(count($res)){
				case 0://не найдено ни одного похожего названия
					$this->conversationStorage->deleteConversation();
					return new DirectedOutgoingMessage(
						$this->user_id,
						new OutgoingMessage('Не найдено подходящих названий')
					);
					break;
								
				case 1://найдено только одно подходящее название
					$this->conversationStorage->deleteConversation();
					$show = $res[0];
					try{
						$action->execute(
							array(
								':show_id' => $show['id'],
								':user_id' => $this->user_id
							)
						);
					}
					catch(\PDOException $ex){
						$this->tracer->logException(
							'[DB ERROR]', __FILE__, __LINE__, $ex);
						return new DirectedOutgoingMessage(
							$this->user_id,
							new OutgoingMessage('Ошибка в базе. Записал. Починят.')
						); 
					}
					
					return new DirectedOutgoingMessage(
						$this->user_id,
						new OutgoingMessage("$show[title_all] $successText")
					);
					break;
				
				default://подходят несколько вариантов
					$showTitles = array_column($res, 'title_all');
					array_unshift($showTitles, '/cancel');
					array_push($showTitles, '/cancel');
					
					return new DirectedOutgoingMessage(
						$this->user_id,
						new OutgoingMessage(
							'Какой из этих ты имел ввиду:',
							false,
							false,
							$showTitles
						)
					);
					break;
				}
			}
			break;
		case 3:
			$this->conversationStorage->deleteConversation();
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
						':user_id' 		=> $this->user_id,
						':in_out_flag'	=> $in_out_flag,
						':exactShowName' => $conversation[2]
					)
				);
			}
			catch(\PDOException $ex){
				$this->tracer->logException('[DB ERROR]', __FILE__, __LINE__, $ex);
				return new DirectedOutgoingMessage(
					$this->user_id, 
					new OurgoingMessage('Ошибка в базе. Записал. Починят.')
				); 
			}
			
			$res = $query->fetchAll();
			
			if(count($res) === 0){
				return new DirectedOutgoingMessage(
					$this->user_id,
					new OutgoingMessage('Не могу найти такое название.')
				);
			}
			else{
				$show = $res[0];
				try{
					$action->execute(
						array(
							':user_id' => $this->user_id,
							':show_id' => $show['id']
						)
					);
				}
				catch(\PDOException $ex){
					$this->tracer->logException('[DB ERROR]', __FILE__, __LINE__, $ex);
					return new DirectedOutgoingMessage(
						$this->user_id,
						new OutgoingMessage('Ошибка в базе. Записал. Починят.')
					); 
				}
				
				return new DirectedOutgoingMessage(
					$this->user_id,
					new OutgoingMessage("$show[title_all] $successText")
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
	public function processLastUpdate(){
		assert($this->conversationStorage->getConversationSize() > 0);

		$response = null;

		if($this->conversationStorage->getLastMessage() === '/cancel'){
			$response = $this->cancelRequest();
			return $response;
		}

		
		try{
			switch($this->conversationStorage->getFirstMessage()){
			case '/start':
				$response = $this->welcomeUser();
				break;

			case '/cancel':
				$response = $this->cancelRequest();
				break;

			case '/stop':
				$response = $this->deleteUser();
				break;
			
			case '/help':
				$response = $this->showHelp();
				break;
			
			case '/mute':
				$response = $this->toggleMute();
				break;
			
			case '/get_my_shows':
				$response = $this->showUserShows();
				break;
				
			case '/add_show':
				$response = $this->insertOrDeleteShow(true);
				break;
			
			case '/remove_show':
				$response = $this->insertOrDeleteShow(false);
				break;

			default:
				$this->conversationStorage->deleteConversation();
				$response = new DirectedOutgoingMessage(
					$this->user_id,
					new OutgoingMessage('Я хз чё это значит')
				); 
			}
		}
		catch(\Exception $ex){
			$response = new DirectedOutgoingMessage(
				$this->user_id,
				new OutgoingMessage('Произошла ошибка, я сообщу об этом создателю.')
			);
			$this->tracer->logException('[BOT]', __FILE__, __LINE__, $ex);
		}
		
		return $response;
	}
}
		
		


