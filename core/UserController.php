<?php

namespace core;

require_once(__DIR__.'/User.php');
require_once(__DIR__.'/NotificationGenerator.php');
require_once(__DIR__.'/ConversationStorage.php');
require_once(__DIR__.'/BotPDO.php');
require_once(__DIR__.'/OutgoingMessage.php');
require_once(__DIR__.'/../lib/Config.php');
require_once(__DIR__.'/../lib/Tracer/Tracer.php');
require_once(__DIR__.'/../lib/CommandSubstitutor/CommandSubstitutor.php');

abstract class ShowAction{
	const Add = 1;
	const Remove = 2;
	const AddTentative = 3;
}

class UserController{
	private $user;
	private $conversationStorage;
	private $messageDestination;
	private $pdo;
	private $config;
	private $tracer;
	private $coreCommands;
	private $commandSubstitutor;

	public function __construct(User $user){
		$this->tracer = new \Tracer(__CLASS__);
		$this->pdo = \BotPDO::getInstance();
		$this->config = new \Config($this->pdo);
		$this->user = $user;
		$this->conversationStorage = new ConversationStorage($user->getId());

		$this->commandSubstitutor = new \CommandSubstitutor\CommandSubstitutor($this->pdo);
		$this->coreCommands = $this->commandSubstitutor->getCoreCommandsAssociative();
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
					':user_id' => $this->user->getId()
				)
			);

			$res = $getMessagesHistorySize->fetch();
			$count = intval($res[0]);

			if($count > 1){
				return new DirectedOutgoingMessage(
					$this->user->getId(),
					new OutgoingMessage('Мы ведь уже знакомы, правда?')
				);
			}
		}
		catch(\PDOException $ex){
			$this->tracer->logException('[DB]', __FILE__, __LINE__, $ex);
			$username = '';
		}

		$helpCoreCommand = $this->coreCommands[\CommandSubstitutor\CoreCommandMap::Help];

		$welcomingText =
			'Привет!'.PHP_EOL.
			'Я - бот LostFilm updates.'.PHP_EOL.
			'Моя задача - оповестить тебя о выходе новых серий '.
			'твоих любимых сериалов на сайте https://lostfilm.tv/'.PHP_EOL.PHP_EOL.
			"Чтобы узнать что я умею - жми на $helpCoreCommand";
		
		$response = new DirectedOutgoingMessage(
			$this->user->getId(),
			new OutgoingMessage($welcomingText)
		);

		try{
			$notificationGenerator = new NotificationGenerator();
			$adminNotification = $notificationGenerator->newUserEvent($this->user->getId());

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
			$this->user->getId(),
			new OutgoingMessage('Действие отменено.')
		);
	}

	private function deleteUser(){
		$ANSWER_YES = 'Да';
		$ANSWER_NO = 'Нет';

		switch($this->conversationStorage->getConversationSize()){
		case 1:
			$lastChance = 'Точно? Вся информация о тебе будет безвозвратно потеряна...';
			$options = array($ANSWER_YES, $ANSWER_NO);
			$muteCoreCommand = $this->coreCommands[\CommandSubstitutor\CoreCommandMap::Mute];
			
			if($this->user->muted() === false){
				$lastChance .= 
					PHP_EOL.PHP_EOL.
					'Если тебя раздражают уведомления, '.
					"может лучше воспользуешься командой $muteCoreCommand?";
			}

			return new DirectedOutgoingMessage(
				$this->user->getId(),
				new OutgoingMessage(
					$lastChance,
					new MarkupType(MarkupTypeEnum::NoMarkup),
					false,
					array($ANSWER_YES, $ANSWER_NO)
				)
			);
	
			break;
		
		case 2:
			$response = $this->conversationStorage->getLastMessage()->getText();

			switch(strtolower($response)){
			case strtolower($ANSWER_YES):
				$this->conversationStorage->deleteConversation();

				$adminNotification = null;
				
				try{
					$notificationGenerator = new NotificationGenerator();
					$adminNotification = $notificationGenerator->userLeftEvent(
						$this->user->getId()
					);
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
							':user_id' => $this->user->getId()
						)
					);
				}
				catch(\PDOException $ex){
					$this->tracer->logException('[DB ERROR]', __FILE__, __LINE__, $ex);
					$this->conversationStorage->deleteConversation();
					return new DirectedOutgoingMessage(
						$this->user->getId(),
						new OutgoingMessage('Возникла ошибка. Записал, починят.')
					);
				}
				
				$userResponse = new DirectedOutgoingMessage(
					$this->user->getId(),
					new OutgoingMessage('Прощай...')
				);

				if($adminNotification !== null){
					$userResponse->appendMessage($adminNotification);
				}

				return $userResponse;
				
			case strtolower($ANSWER_NO):
				$this->conversationStorage->deleteConversation();
				return new DirectedOutgoingMessage(
					$this->user->getId(),
					new OutgoingMessage('Фух, а то я уже испугался')
				);
			
			default:
				$command = $this->conversationStorage->getLastMessage()->getCoreCommand();
				if(
					$this->user->muted() === false						&&
					$command !== null									&&
					$command->getId() === \CommandSubstitutor\CoreCommandMap::Mute
				){
					return $this->toggleMute();
				}

				$this->repeatQuestion();

				return new DirectedOutgoingMessage(
					$this->user->getId(),
					new OutgoingMessage(
						"Давай конкретнее, либо $ANSWER_YES, либо $ANSWER_NO",
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

		$addShowCoreCommand = $this->coreCommands[\CommandSubstitutor\CoreCommandMap::AddShow];
		$removeShowCoreCommand = $this->coreCommands[\CommandSubstitutor\CoreCommandMap::RemoveShow];
		$getMyShowsCoreCommand = $this->coreCommands[\CommandSubstitutor\CoreCommandMap::GetMyShows];
		$muteCoreCommand = $this->coreCommands[\CommandSubstitutor\CoreCommandMap::Mute];
		$cancelCoreCommand = $this->coreCommands[\CommandSubstitutor\CoreCommandMap::Cancel];
		$helpCoreCommand = $this->coreCommands[\CommandSubstitutor\CoreCommandMap::Help];
		$stopCoreCommand = $this->coreCommands[\CommandSubstitutor\CoreCommandMap::Stop];
		$getShareButtonCoreCommand = $this->coreCommands[\CommandSubstitutor\CoreCommandMap::GetShareButton];
		$donateCoreCommand = $this->coreCommands[\CommandSubstitutor\CoreCommandMap::Donate];
		
		$helpText =
			'LostFilm updates - бот, который оповещает '									.
			'о новых сериях на https://lostfilm.tv/'								.PHP_EOL
																					.PHP_EOL.
			'Список команд:'														.PHP_EOL.
			"$addShowCoreCommand - Добавить уведомления о сериале"					.PHP_EOL.
			"$removeShowCoreCommand - Удалить уведомления о сериале"				.PHP_EOL.
			"$getMyShowsCoreCommand - Показать, на что ты подписан"					.PHP_EOL.
			"$muteCoreCommand - Выключить уведомления"								.PHP_EOL.
			"$cancelCoreCommand - Отменить команду"									.PHP_EOL.
			"$helpCoreCommand - Показать это сообщение"								.PHP_EOL.
			"$donateCoreCommand - Задонатить пару баксов на доширак создателю"		.PHP_EOL.
			"$getShareButtonCoreCommand - Поделиться контактом бота"				.PHP_EOL.
			"$stopCoreCommand - Удалиться из контакт-листа бота"					.PHP_EOL
																					.PHP_EOL.
			'Telegram/VK создателя: @libertypaul'									.PHP_EOL.
			'Ну и электропочта есть, куда ж без неё: admin@libertypaul.ru'			.PHP_EOL.
			'Исходники бота есть на GitHub: '											.
			'https://github.com/LibertyPaul/LostFilmUpdatesBot'						.PHP_EOL
																					.PHP_EOL.
			'Создатель бота не имеет никакого отношеня к проекту lostfilm.tv.';
		
		return new DirectedOutgoingMessage(
			$this->user->getId(),
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
				':user_id' => $this->user->getId()
			)
		);
		
		$userShows = $getUserShowsQuery->fetchAll();
		
		if(count($userShows) === 0){
			$addShowCoreCommand = $this->coreCommands[\CommandSubstitutor\CoreCommandMap::AddShow];
			return new DirectedOutgoingMessage(
				$this->user->getId(),
				new OutgoingMessage("Пока тут пусто. Добавь парочку командой $addShowCoreCommand")
			);
		}

		$hasOutdated = false;

		$rows = array();

		foreach($userShows as $show){
			$icon = '•';
			if($show['onAir'] === 'N'){
				$icon = '✕';
				$hasOutdated = true;
			}

			$rows[] = sprintf('%s %s', $icon, $show['title']).PHP_EOL.PHP_EOL;
		}

		if($hasOutdated){
			$rows[] = '<i>• - сериал выходит</i>';
			$rows[] = '<i>✕ - сериал закончен</i>';
		}

		$messageParts = array();
		$currentPart = '';

		foreach($rows as $row){
			if(strlen($currentPart) + strlen($row) > 4000){
				$messageParts[] = $currentPart;
				$currentPart = '';
			}

			$currentPart .= $row;
		}
		
		if(strlen($currentPart) > 0){
			$messageParts[] = $currentPart;
		}
		
		$markupType = new MarkupType(MarkupTypeEnum::HTML);

		$outgoingMessage = new OutgoingMessage($messageParts[0], $markupType);
		for($i = 1; $i < count($messageParts); ++$i){
			$nextMessage = new OutgoingMessage($messageParts[$i], $markupType);
			$outgoingMessage->appendMessage($nextMessage);
		}

		return new DirectedOutgoingMessage($this->user->getId(), $outgoingMessage);
	}
	
	private function toggleMute(){
		$this->conversationStorage->deleteConversation();

		$toggleMuteQuery = $this->pdo->prepare("
			UPDATE `users`
			SET `mute` = (
				CASE `mute`
					WHEN 'Y' THEN 'N'
					WHEN 'N' THEN 'Y'
				END
			)
			WHERE `id` = :user_id
		");
		
		try{
			$toggleMuteQuery->execute(
				array(
					':user_id'	=> $this->user->getId()
				)
			);
		}
		catch(\PDOException $ex){
			$this->tracer->logException('[DB ERROR]', __FILE__, __LINE__, $ex);
			return new DirectedOutgoingMessage(
				$this->user->getId(),
				new OutgoingMessage('Возникла ошибка в базе. Записал. Починят.')
			);
		}
		
		if($this->user->muted()){
			$action = 'Включил';
		}
		else{
			$action = 'Выключил';
		}
		
		return new DirectedOutgoingMessage(
			$this->user->getId(),
			new OutgoingMessage("$action все уведомления")
		);
	}
	
	private function insertOrDeleteShow($showAction){
		$this->tracer->logDebug('[insertOrDeleteShow]', __FILE__, __LINE__, "[$showAction]");

		switch($showAction){
		case ShowAction::Add:
		case ShowAction::AddTentative:
			$successText = 'добавлен';
			$action = $this->pdo->prepare('
				INSERT INTO `tracks` (`user_id`, `show_id`) 
				VALUES (:user_id, :show_id)
			');
			
			break;

		case ShowAction::Remove: 
			$successText = 'удален';
			$action = $this->pdo->prepare('
				DELETE FROM `tracks`
				WHERE `user_id` = :user_id
				AND   `show_id` = :show_id
			');
			
			break;
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
					XOR :showAction
				)
				AND ((`shows`.`onAir` = 'Y') OR NOT :showAction)
				ORDER BY `title_ru`, `title_en`
			");

			try{
				$query->execute(
					array(
						':user_id'		=> $this->user->getId(),
						':showAction'	=> $showAction !== ShowAction::Remove
					)
				);
			}
			catch(\PDOException $ex){
				$this->tracer->logException('[DB ERROR]', __FILE__, __LINE__, $ex);
				$this->conversationStorage->deleteConversation();
				return new DirectedOutgoingMessage(
					$this->user->getId(),
					new OutgoingMessage('Ошибка в базе. Записал. Починят.')
				); 
			}
			
			$showTitles = $query->fetchAll(\PDO::FETCH_COLUMN, 'title');
			
			if(count($showTitles) === 0){
				$this->conversationStorage->deleteConversation();
				
				switch($showAction){
				case ShowAction::Add:
				case ShowAction::AddTentative:
					$text =
						'Ты подписан на все сериалы.'.PHP_EOL.
						'И как ты успеваешь их все смотреть??';
					
					break;

				case ShowAction::Remove:
					$addShowCoreCommand = $this->coreCommands[\CommandSubstitutor\CoreCommandMap::AddShow];
					$text = "Нечего удалять. Для начала добавь пару сериалов командой [$addShowCoreCommand].";
					
					break;
				}
				
				return new DirectedOutgoingMessage(
					$this->user->getId(),
					new OutgoingMessage($text)
				);
			}
			else{
				$text = 'Как называется сериал?'.PHP_EOL.
						'Выбери из списка / введи пару слов из названия или '.
						'продиктуй их в голосовом сообщении';
				
				array_unshift($showTitles, '/cancel');
				array_push($showTitles, '/cancel');
				
				return new DirectedOutgoingMessage(
					$this->user->getId(),
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
				WHERE ((`shows`.`onAir` = 'Y') OR NOT :showAction)
				AND (
					`id` IN(
						SELECT `show_id`
						FROM `tracks`
						WHERE `user_id` = :user_id
					)
					XOR :showAction
				)
				HAVING `title_all` = :title
				ORDER BY `title_ru`, `title_en`
			");
			
			try{
				$messageText = $this->conversationStorage->getLastMessage()->getText();
				$getShowId->execute(
					array(
						':title'		=> $messageText,
						':user_id'		=> $this->user->getId(),
						':showAction'	=> $showAction !== ShowAction::Remove
					)
				);
			}
			catch(\PDOException $ex){
				$this->tracer->logException('[DB ERROR]', __FILE__, __LINE__, $ex);
				$this->conversationStorage->deleteConversation();
				return new DirectedOutgoingMessage(
					$this->user->getId(),
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
							':user_id' => $this->user->getId()
						)
					);
				}
				catch(\PDOException $ex){
					$this->tracer->logException('[DB ERROR]', __FILE__, __LINE__, $ex);
					return new DirectedOutgoingMessage(
						$this->user->getId(),
						new OutgoingMessage('Ошибка в базе. Записал. Починят.')
					); 
				}
				
				return new DirectedOutgoingMessage(
					$this->user->getId(),
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
						XOR :showAction
					)
					AND ((`shows`.`onAir` = 'Y') OR NOT :showAction)
					HAVING `score` > 0.1
					ORDER BY `score` DESC
				");
				
				try{
					$messageText = $this->conversationStorage->getLastMessage()->getText();
					$query->execute(
						array(
							':user_id' 		=> $this->user->getId(),
							':show_name'	=> $messageText,
							':showAction'	=> $showAction !== ShowAction::Remove
						)
					);
				}
				catch(\PDOException $ex){
					$this->tracer->logException('[DB ERROR]', __FILE__, __LINE__, $ex);
					$this->conversationStorage->deleteConversation();
					return new DirectedOutgoingMessage(
						$this->user->getId(),
						new OutgoingMessage('Ошибка в базе. Записал. Починят.')
					); 
				}
				
				$res = $query->fetchAll();
				
				switch(count($res)){
				case 0://не найдено ни одного похожего названия

					$query->debugDumpParams();

					$this->conversationStorage->deleteConversation();
					return new DirectedOutgoingMessage(
						$this->user->getId(),
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
								':user_id' => $this->user->getId()
							)
						);
					}
					catch(\PDOException $ex){
						$this->tracer->logException(
							'[DB ERROR]', __FILE__, __LINE__, $ex);
						return new DirectedOutgoingMessage(
							$this->user->getId(),
							new OutgoingMessage('Ошибка в базе. Записал. Починят.')
						); 
					}
					
					return new DirectedOutgoingMessage(
						$this->user->getId(),
						new OutgoingMessage("$show[title_all] $successText")
					);
					break;
				
				default://подходят несколько вариантов
					$showTitles = array_column($res, 'title_all');
					array_unshift($showTitles, '/cancel');
					array_push($showTitles, '/cancel');
					
					return new DirectedOutgoingMessage(
						$this->user->getId(),
						new OutgoingMessage(
							'Какой из этих ты имеешь в виду:',
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
				WHERE ((`shows`.`onAir` = 'Y') OR NOT :showAction)
				AND (
					`id` IN(
						SELECT `show_id`
						FROM `tracks`
						WHERE `user_id` = :user_id
					)
					XOR :showAction
				)
				HAVING `title_all` = :exactShowName
			");
			
			try{
				$query->execute(
					array(
						':user_id' 			=> $this->user->getId(),
						':showAction'		=> $showAction !== ShowAction::Remove,
						':exactShowName'	=> $messageText
					)
				);
			}
			catch(\PDOException $ex){
				$this->tracer->logException('[DB ERROR]', __FILE__, __LINE__, $ex);
				return new DirectedOutgoingMessage(
					$this->user->getId(), 
					new OurgoingMessage('Ошибка в базе. Записал. Починят.')
				); 
			}
			
			$res = $query->fetchAll();
			
			if(count($res) === 0){
				return new DirectedOutgoingMessage(
					$this->user->getId(),
					new OutgoingMessage('Не могу найти такое название.')
				);
			}
			else{
				$show = $res[0];
				try{
					$action->execute(
						array(
							':user_id' => $this->user->getId(),
							':show_id' => $show['id']
						)
					);
				}
				catch(\PDOException $ex){
					$this->tracer->logException('[DB ERROR]', __FILE__, __LINE__, $ex);
					return new DirectedOutgoingMessage(
						$this->user->getId(),
						new OutgoingMessage('Ошибка в базе. Записал. Починят.')
					); 
				}
				
				return new DirectedOutgoingMessage(
					$this->user->getId(),
					new OutgoingMessage("$show[title_all] $successText")
				);
			}
			break;
		}
	}

	private function getShareButton(){
		$this->conversationStorage->deleteConversation();

		$shareButton = new InlineOption('Поделиться', InlineOptionType::ShareButton, '');

		return new DirectedOutgoingMessage(
			$this->user->getId(),
			new OutgoingMessage(
				'Вот тебе кнопочка. Нажми и выбери чат в который отправить мой контакт.',
				new MarkupType(MarkupTypeEnum::NoMarkup),
				false,
				null,
				array($shareButton)
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
			$this->user->getId(),
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
		if($allowedUserId === null || $this->user->getId() !== $allowedUserIdInt){
			$this->conversationStorage->deleteConversation();

			return new DirectedOutgoingMessage(
				$this->user->getId(),
				new OutgoingMessage('Z@TTР3LL|3Н0')
			);
		}

		switch($this->conversationStorage->getConversationSize()){
		case 1:
			return new DirectedOutgoingMessage(
				$this->user->getId(),
				new OutgoingMessage('Окей, что раcсылать?')
			);

			break;

		case 2:
			return new DirectedOutgoingMessage(
				$this->user->getId(),
				new OutgoingMessage(
					'Пуш уведомление?',
					new MarkupType(MarkupTypeEnum::NoMarkup),
					false,
					array('Да', 'Нет', '/cancel')
				)
			);

		case 3:
			return new DirectedOutgoingMessage(
				$this->user->getId(),
				new OutgoingMessage(
					'Будет ли разметка?',
					new MarkupType(MarkupTypeEnum::NoMarkup),
					false,
					array('HTML', 'Telegram API Markup', 'Без разметки', '/cancel')
				)
			);

		case 4:
			return new DirectedOutgoingMessage(
				$this->user->getId(),
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

				$response = new DirectedOutgoingMessage($this->user->getId(),	$example);
				return $response;
			}
			else{
				$this->conversationStorage->deleteConversation();
				return new DirectedOutgoingMessage(
					$this->user->getId(),
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
					$this->user->getId(),
					new OutgoingMessage('Рассылка отменена.')
				);
			}

			$started = new DirectedOutgoingMessage(
				$this->user->getId(),
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
				$this->user->getId(),
				new OutgoingMessage(sprintf('Отправил %d сообщений(е/я).', $count))
			);

			$broadcastChain->appendMessage($confirmMessage);

			return $broadcastChain;
			
			break;
		}
	}

	public function processMessage(IncomingMessage $incomingMessage){
		try{

			$this->conversationStorage->appendMessage($incomingMessage);

			$currentCommand = $incomingMessage->getCoreCommand();
			if($currentCommand !== null){
				if($currentCommand->getId() === \CommandSubstitutor\CoreCommandMap::Cancel){
					return $this->cancelRequest();
				}
			}

			$initialMessage = $this->conversationStorage->getFirstMessage();

			# Case when user just typed a show name
			if($initialMessage->getCoreCommand() === null){
				$assumedCommand = $this->commandSubstitutor->getCoreCommand(
						\CommandSubstitutor\CoreCommandMap::AddShowTentative
				);

				$this->tracer->logfEvent(
					'[o]', __FILE__, __LINE__,
					'Bare text without a command [%s]. Assuming to be [%s].',
					$initialMessage->getText(),
					$assumedCommand->getText()
				);

				$assumedMessage = new IncomingMessage($assumedCommand, 'Dummy');
				$this->conversationStorage->prependMessage($assumedMessage);
			}
			
			switch($this->conversationStorage->getFirstMessage()->getCoreCommand()->getId()){
				case \CommandSubstitutor\CoreCommandMap::Start:
					return $this->welcomeUser();

				case \CommandSubstitutor\CoreCommandMap::Cancel:
					return $this->cancelRequest();

				case \CommandSubstitutor\CoreCommandMap::Stop:
					return $this->deleteUser();
				
				case \CommandSubstitutor\CoreCommandMap::Help:
					return $this->showHelp();
				
				case \CommandSubstitutor\CoreCommandMap::Mute:
					return $this->toggleMute();
				
				case \CommandSubstitutor\CoreCommandMap::GetMyShows:
					return $this->showUserShows();
					
				case \CommandSubstitutor\CoreCommandMap::AddShow:
					return $this->insertOrDeleteShow(ShowAction::Add);
				
				case \CommandSubstitutor\CoreCommandMap::RemoveShow:
					# TODO: Telegram rejects a keyboard with all shows.
					# Need an alternative way to promps a choice.
					return $this->insertOrDeleteShow(ShowAction::Remove);

				case \CommandSubstitutor\CoreCommandMap::AddShowTentative:
					return $this->insertOrDeleteShow(ShowAction::AddTentative);

				case \CommandSubstitutor\CoreCommandMap::GetShareButton:
					return $this->getShareButton();

				case \CommandSubstitutor\CoreCommandMap::Donate:
					return $this->getDonateOptions();

				case \CommandSubstitutor\CoreCommandMap::Broadcast:
					return $this->broadcast();

				default:
					$this->tracer->logError(
						'[COMMAND]', __FILE__, __LINE__,
						'Unknown command:'.PHP_EOL.
						print_r($initialCommand, true)
					);
					throw \LogicException('Unknown command');
			}
		}
		catch(\Throwable $ex){
			$this->tracer->logException('[BOT]', __FILE__, __LINE__, $ex);
			$this->conversationStorage->deleteConversation();

			return new DirectedOutgoingMessage(
				$this->user->getId(),
				new OutgoingMessage('Произошла ошибка, я сообщу об этом создателю.')
			);
		}
	}
}
		
		


