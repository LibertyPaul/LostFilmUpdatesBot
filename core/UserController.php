<?php

namespace core;

require_once(__DIR__.'/NotificationGenerator.php');
require_once(__DIR__.'/ConversationStorage.php');
require_once(__DIR__.'/BotPDO.php');
require_once(__DIR__.'/OutgoingMessage.php');
require_once(__DIR__.'/../lib/Config.php');
require_once(__DIR__.'/../lib/Tracer/Tracer.php');

class UserController{
	private $user_id; # TODO: load full users record
	private $conversationStorage;
	private $messageDestination;
	private $pdo;
	private $config;
	private $tracer;

	public function __construct($user_id, ConversationStorage $conversationStorage){
		$this->tracer = new \Tracer(__CLASS__);
		$this->pdo = \BotPDO::getInstance();
		$this->config = new \Config($this->pdo);

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
					new OutgoingMessage('Мы ведь уже знакомы, правда?')
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

			if($adminNotification !== null){
				$response->appendMessage($adminNotification);
			}
		}
		catch(\Throwable $ex){
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
		$ANSWER_YES = 'да';
		$ANSWER_NO = 'нет';
		
		switch($this->conversationStorage->getConversationSize()){
		case 1:
			return new DirectedOutgoingMessage(
				$this->user_id,
				new OutgoingMessage(
					'Ты уверен? Вся информация о тебе будет безвозвратно потеряна...',
					new MarkupType(MarkupTypeEnum::NoMarkup),
					false,
					array($ANSWER_YES, $ANSWER_NO)
				)
			);
	
			break;
		
		case 2:
			$response = $this->conversationStorage->getLastMessage()->getText();
			switch(strtolower($response)){
			case $ANSWER_YES:
				$this->conversationStorage->deleteConversation();

				$adminNotification = null;
				
				try{
					$notificationGenerator = new NotificationGenerator();
					$adminNotification = $notificationGenerator->userLeftEvent($this->user_id);
				}
				catch(\Throwable $ex){
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
				
				$userResponse = new DirectedOutgoingMessage(
					$this->user_id,
					new OutgoingMessage('Прощай...')
				);

				if($adminNotification !== null){
					$userResponse->appendMessage($adminNotification);
				}

				return $userResponse;
				
			case $ANSWER_NO:
				$this->conversationStorage->deleteConversation();
				return new DirectedOutgoingMessage(
					$this->user_id,
					new OutgoingMessage('Фух, а то я уже испугался')
				);
			
			default:
				$this->repeatQuestion();
				return new DirectedOutgoingMessage(
					$this->user_id,
					new OutgoingMessage(
						'Давай конкретнее, либо да, либо нет',
						new MarkupType(MarkupTypeEnum::NoMarkup),
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
			'/add_show - Включить уведомления о сериале'						.PHP_EOL.
			'/remove_show - Выключить уведомления о сериале'					.PHP_EOL.
			'/get_my_shows - Показать включенные уведомления'					.PHP_EOL.
			'/mute - Приостановить рассылку уведомлений'						.PHP_EOL.
			'/cancel - Отменить команду'										.PHP_EOL.
			'/help - Показать это сообщение'									.PHP_EOL.
			'/donate - Задонатить пару баксов'									.PHP_EOL.
			'/share - Поделиться ботом с другом'								.PHP_EOL.
			'/stop - Удалиться из контакт-листа бота'							.PHP_EOL
																				.PHP_EOL.
			'Telegram создателя: @libertypaul'									.PHP_EOL.
			'Ну и электропочта есть, куда ж без неё: admin@libertypaul.ru'		.PHP_EOL.
			'Исходники бота есть на GitHub: '									.PHP_EOL.
			'https://github.com/LibertyPaul/LostFilmUpdatesBot';
		
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

		$text = 'Ваши сериалы:'.PHP_EOL.PHP_EOL;
		
		foreach($userShows as $show){
			$icon = '•';
			if($show['onAir'] === 'N'){
				$icon = '✕';
			}

			$showRow = str_replace(
				array('#ICON', '#TITLE'),
				array($icon, $show['title']),
				'#ICON #TITLE'.PHP_EOL.PHP_EOL
			);

			if(strlen($text) + strlen($showRow) > 4000){
				$parts[] = $text;
				$text = '';
			}

			$text .= $showRow;
		}
		
		$text .= PHP_EOL."• - сериал выходит";
		$text .= PHP_EOL."✕ - сериал закончен";

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
				$text = 'Как называется сериал?'								.PHP_EOL.
						'Выбери из списка / введи пару слов из названия или '	.PHP_EOL.
						'продиктуй их в голосовом сообщении';
				
				array_unshift($showTitles, '/cancel');
				array_push($showTitles, '/cancel');
				
				return new DirectedOutgoingMessage(
					$this->user_id,
					new OutgoingMessage(
						$text,
						new MarkupType(MarkupTypeEnum::NoMarkup),
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
				$messageText = $this->conversationStorage->getLastMessage()->getText();
				$getShowId->execute(
					array(
						':title'		=> $messageText,
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
				# Совпадения не найдено. Скорее всего юзер ввел не полное название.
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
					$messageText = $this->conversationStorage->getLastMessage()->getText();
					$query->execute(
						array(
							':user_id' 		=> $this->user_id,
							':show_name'	=> $messageText,
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
							new MarkupType(MarkupTypeEnum::NoMarkup),
							false,
							$showTitles
						)
					);
					break;
				}
			}
			break;
		case 3:
			$messageText = $this->conversationStorage->getLastMessage()->getText();
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
						':user_id' 			=> $this->user_id,
						':in_out_flag'		=> $in_out_flag,
						':exactShowName'	=> $messageText
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

	private function getShareButton(){
		$this->conversationStorage->deleteConversation();

		$donateButton = new InlineOption('Поделиться', InlineOptionType::ShareButton, '');

		return new DirectedOutgoingMessage(
			$this->user_id,
			new OutgoingMessage(
				'Вот тебе кнопочка:',
				new MarkupType(MarkupTypeEnum::NoMarkup),
				false,
				null,
				array($donateButton)
			)
		);
	}

	private function getDonateOptions(){
		$this->conversationStorage->deleteConversation();
		$YandexMoneyButton = null;
		$PayPalButton = null;

		$res = $this->pdo->query("
			SELECT `value`
			FROM `config`
			WHERE `section` = 'Donate'
			AND `item` = 'Yandex.Money'
		");

		$res = $res->fetch();
		if($res !== false){
			$YandexMoneyButton = new InlineOption(
				'Яндекс.Деньги / Visa / Mastercard',
				InlineOptionType::ExternalLink,
				$res[0]
			);
		}

		$res = $this->pdo->query("
			SELECT `value`
			FROM `config`
			WHERE `section` = 'Donate'
			AND `item` = 'PayPal'
		");

		$res = $res->fetch();
		if($res !== false){
			$PayPalButton = new InlineOption(
				'PayPal / Visa / Mastercard / American Express / и т.д.',
				InlineOptionType::ExternalLink,
				$res[0]
			);
		}

		$inlineOptions = array();
		if($YandexMoneyButton !== null){
			$inlineOptions[] = $YandexMoneyButton;
		}

		if($PayPalButton !== null){
			$inlineOptions[] = $PayPalButton;
		}

		if(empty($inlineOptions)){
			throw new \RuntimeException('No Donate URL is defined');
		}

		if(count($inlineOptions) > 1){
			$phrase = 'Выбирай любой вариант:';
		}
		else{
			$phrase = 'Вот тебе кнопочка:';
		}

		return new DirectedOutgoingMessage(
			$this->user_id,
			new OutgoingMessage(
				$phrase,
				new MarkupType(MarkupTypeEnum::NoMarkup),
				false,
				null,
				$inlineOptions
			)
		);
	}

	private function buildBroadcastMessage(){
		$text = $this->conversationStorage->getMessage(1)->getText();
		$enablePush = $this->conversationStorage->getMessage(2)->getText();
		$markup = $this->conversationStorage->getMessage(3)->getText();
		$URLExpand = $this->conversationStorage->getMessage(4)->getText();

		switch($enablePush){
			case 'Да':
				$disablePush = false;
				break;

			case 'Нет':
				$disablePush = true;
				break;

			default:
				return array(
					'success' => false,
					'why' => "Disable Push Flag=[$enablePush]"
				);
		}

		switch($markup){
			case 'HTML':
				$markupType = MarkupTypeEnum::HTML;
				break;
			
			case 'Telegram API Markup':
				$markupType = MarkupTypeEnum::Telegram;
				break;			

			case 'Без разметки':
				$markupType = MarkupTypeEnum::NoMarkup;
				break;

			default:
				return array(
					'success' => false,
					'why' => "Markup=[$markup]"
				);
		}

		switch($URLExpand){
			case 'Да':
				$URLExpand = true;
				break;

			case 'Нет':
				$URLExpand = false;
				break;

			default:
				return array(
					'success' => false,
					'why' => "URL Expand Flag=[$URLExpand]"
				);
		}
		

		$message = new OutgoingMessage(
			$text,
			new MarkupType($markupType),
			$URLExpand,
			null,
			null,
			$disablePush
		);
		
		return array(
			'success' => true,
			'message' => $message
		);
	}
	
	private function broadcast(){
		$allowedUserId = $this->config->getValue('Broadcast', 'Allowed User Id');
		$allowedUserIdInt = intval($allowedUserId);
		if($allowedUserId === null || $this->user_id !== $allowedUserIdInt){
			$this->conversationStorage->deleteConversation();

			return new DirectedOutgoingMessage(
				$this->user_id,
				new OutgoingMessage('Z@TTР3LL|3Н0')
			);
		}

		switch($this->conversationStorage->getConversationSize()){
		case 1:
			return new DirectedOutgoingMessage(
				$this->user_id,
				new OutgoingMessage('Окей, что раcсылать?')
			);

			break;

		case 2:
			return new DirectedOutgoingMessage(
				$this->user_id,
				new OutgoingMessage(
					'Пуш уведомление?',
					new MarkupType(MarkupTypeEnum::NoMarkup),
					false,
					array('Да', 'Нет', '/cancel')
				)
			);

		case 3:
			return new DirectedOutgoingMessage(
				$this->user_id,
				new OutgoingMessage(
					'Будет ли разметка?',
					new MarkupType(MarkupTypeEnum::NoMarkup),
					false,
					array('HTML', 'Telegram API Markup', 'Без разметки', '/cancel')
				)
			);

		case 4:
			return new DirectedOutgoingMessage(
				$this->user_id,
				new OutgoingMessage(
					'Превью ссылок?',
					new MarkupType(MarkupTypeEnum::NoMarkup),
					false,
					array('Да', 'Нет', '/cancel')
				)
			);
			
		case 5:
			$result = $this->buildBroadcastMessage();

			if($result['success']){
				$example = new OutgoingMessage('Вот что получилось:');
				$example->appendMessage($result['message']);
				$confirm = new OutgoingMessage(
					'Отправляем?',
					new MarkupType(MarkupTypeEnum::NoMarkup),
					false,
					array('Да', 'Нет')
				);
				$example->appendMessage($confirm);

				$response = new DirectedOutgoingMessage($this->user_id,	$example);
				return $response;
			}
			else{
				$this->conversationStorage->deleteConversation();
				return new DirectedOutgoingMessage(
					$this->user_id,
					new OutgoingMessage('Ты накосячил!. '.$result['why'])
				);
			}

			break;

		case 6:
			$result = $this->buildBroadcastMessage();
			assert($result['success']);
			$confirmation = $this->conversationStorage->getLastMessage()->getText();

			$this->conversationStorage->deleteConversation();

			if($confirmation !== 'Да'){
				return new DirectedOutgoingMessage(
					$this->user_id,
					new OutgoingMessage('Рассылка отменена.')
				);
			}

			$started = new DirectedOutgoingMessage(
				$this->user_id,
				new OutgoingMessage('Начал рассылку.')
			);

			$message = $result['message'];
			$userIdsQuery = $this->pdo->prepare('SELECT `id` FROM `users`');
			$userIdsQuery->execute();

			$count = 0;

			$broadcastChain = null;

			while($user = $userIdsQuery->fetch()){
				$user_id = intval($user['id']);
				$current = new DirectedOutgoingMessage($user_id, $message);
				$current->appendMessage($broadcastChain);
				$broadcastChain = $current;
				++$count;
			}

			$confirmMessage = new DirectedOutgoingMessage(
				$this->user_id,
				new OutgoingMessage(sprintf('Отправил %d сообщений(е/я).', $count))
			);

			$broadcastChain->appendMessage($confirmMessage);

			return $broadcastChain;
			
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
		try{
			if($this->conversationStorage->getConversationSize() < 1){
				throw new \LogicException('Empty Conversation Storage');
			}
			
			$response = null;

			$currentMessage = $this->conversationStorage->getLastMessage();
			$currentCommand = $currentMessage->getUserCommand();
			
			if($currentCommand !== null){
				if($currentCommand->getCommandId() === UserCommandMap::Cancel){
					return $this->cancelRequest();
				}
			}

			$initialMessage = $this->conversationStorage->getFirstMessage();
			$initialCommand = $initialMessage->getUserCommand();
			if($initialCommand === null){
				$initialText = $initialMessage->getText();

				$response = new DirectedOutgoingMessage(
					$this->user_id,
					new OutgoingMessage(
						sprintf('Я хз чё "%s" значит.', $initialText).PHP_EOL.
						'Нажми на /help чтобы увидеть список команд.'
					)
				);

				$response->appendMessage($this->cancelRequest());
				return $response;
			}

			switch($initialCommand->getCommandId()){
				case UserCommandMap::Start:
					$response = $this->welcomeUser();
					break;

				case UserCommandMap::Cancel:
					$response = $this->cancelRequest();
					break;

				case UserCommandMap::Stop:
					$response = $this->deleteUser();
					break;
				
				case UserCommandMap::Help:
					$response = $this->showHelp();
					break;
				
				case UserCommandMap::Mute:
					$response = $this->toggleMute();
					break;
				
				case UserCommandMap::GetMyShows:
					$response = $this->showUserShows();
					break;
					
				case UserCommandMap::AddShow:
					$response = $this->insertOrDeleteShow(true);
					break;
				
				case UserCommandMap::RemoveShow:
					$response = $this->insertOrDeleteShow(false);
					break;

				case UserCommandMap::GetShareButton:
					$response = $this->getShareButton();
					break;

				case UserCommandMap::Donate:
					$response = $this->getDonateOptions();
					break;

				case UserCommandMap::Broadcast:
					$response = $this->broadcast();
					break;
			}
		}
		catch(\Throwable $ex){
			$response = new DirectedOutgoingMessage(
				$this->user_id,
				new OutgoingMessage('Произошла ошибка, я сообщу об этом создателю.')
			);
			$this->tracer->logException('[BOT]', __FILE__, __LINE__, $ex);
		}
		
		return $response;
	}
}
		
		


