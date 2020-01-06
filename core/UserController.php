<?php

namespace core;

require_once(__DIR__.'/NotificationGenerator.php');
require_once(__DIR__.'/ConversationStorage.php');
require_once(__DIR__.'/BotPDO.php');
require_once(__DIR__.'/OutgoingMessage.php');
require_once(__DIR__.'/../lib/Config.php');
require_once(__DIR__.'/../lib/Tracer/Tracer.php');
require_once(__DIR__.'/../lib/CommandSubstitutor/CommandSubstitutor.php');

require_once(__DIR__.'/../lib/DAL/Shows/ShowsAccess.php');
require_once(__DIR__.'/../lib/DAL/Shows/Show.php');
require_once(__DIR__.'/../lib/DAL/Users/UsersAccess.php');
require_once(__DIR__.'/../lib/DAL/Users/User.php');
require_once(__DIR__.'/../lib/DAL/Tracks/TracksAccess.php');
require_once(__DIR__.'/../lib/DAL/Tracks/Track.php');


class UserController{
	private $user;
	private $conversationStorage;
	private $messageDestination;
	private $pdo;
	private $config;
	private $tracer;
	private $coreCommands;
	private $commandSubstitutor;

	private $showsAccess;
	private $usersAccess;
	private $tracksAccess;

	public function __construct(User $user){
		$this->tracer = new \Tracer(__CLASS__);
		$this->pdo = \BotPDO::getInstance();
		$this->config = new \Config($this->pdo);
		$this->user = $user;
		$this->conversationStorage = new ConversationStorage($user->getId());

		$this->commandSubstitutor = new \CommandSubstitutor\CommandSubstitutor($this->pdo);
		$this->coreCommands = $this->commandSubstitutor->getCoreCommandsAssociative();

		$this->showsAccess	= new \DAL\ShowsAccess($this->pdo);
		$this->usersAccess	= new \DAL\UsersAccess($this->pdo);
		$this->tracksAccess	= new \DAL\TracksAccess($this->pdo);
	}

	private function repeatQuestion(){
		$this->conversationStorage->deleteLastMessage();
	}
	
	private function welcomeUser(){
		$this->conversationStorage->deleteConversation();

		$tracksCount = $this->tracksAccess->getUserTracksCount($this->user->getId());
		if($tracksCount > 0){
			$getMyShowsCoreCommand = $this->coreCommands[\CommandSubstitutor\CoreCommandMap::GetMyShows];
			return new DirectedOutgoingMessage(
				$this->user,
				new OutgoingMessage(
					"Мы ведь уже знакомы, правда?".PHP_EOL.
					"Чтобы посмотреть свои подписки - жми на $getMyShowsCoreCommand."
				)
			);
		}

		$helpCoreCommand = $this->coreCommands[\CommandSubstitutor\CoreCommandMap::Help];

		$welcomingText =
			'Привет!'.PHP_EOL.
			'Я - бот LostFilm updates.'.PHP_EOL.
			'Моя задача - оповестить тебя о выходе новых серий '.
			'твоих любимых сериалов на сайте https://lostfilm.tv/'.PHP_EOL.PHP_EOL.
			"Чтобы узнать что я умею - жми на $helpCoreCommand";
		
		$response = new DirectedOutgoingMessage(
			$this->user,
			new OutgoingMessage($welcomingText)
		);

		try{
			$notificationGenerator = new NotificationGenerator();
			$adminNotification = $notificationGenerator->newUserEvent($this->user);

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
		if($this->conversationStorage->getConversationSize() > 1){
			$text = 'Действие отменено.';
		}
		else{
			$text = 'Нечего отменять.';
		}

		$this->conversationStorage->deleteConversation();
		return new DirectedOutgoingMessage($this->user, new OutgoingMessage($text));
	}

	private function deleteUser(){
		$ANSWER_YES = 'Да';
		$ANSWER_NO = 'Нет';

		switch($this->conversationStorage->getConversationSize()){
		case 1:
			$lastChance = 'Точно? Вся информация о тебе будет безвозвратно потеряна...';
			$options = array($ANSWER_YES, $ANSWER_NO);
			$muteCoreCommand = $this->coreCommands[\CommandSubstitutor\CoreCommandMap::Mute];
			
			if($this->user->isMuted() === false){
				$lastChance .= 
					PHP_EOL.PHP_EOL.
					'Если тебя раздражают уведомления, '.
					"может лучше воспользуешься командой $muteCoreCommand?";
			}

			return new DirectedOutgoingMessage(
				$this->user,
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

				$this->user->markDeleted();
				$this->usersAccess->updateUser($this->user);
				
				$userResponse = new DirectedOutgoingMessage(
					$this->user,
					new OutgoingMessage('Прощай...')
				);
				
				try{
					$notificationGenerator = new NotificationGenerator();
					$adminNotification = $notificationGenerator->userLeftEvent($this->user);
					if($adminNotification !== null){
						$userResponse->appendMessage($adminNotification);
					}
				}
				catch(\Throwable $ex){
					$this->tracer->logException($ex);
				}

				return $userResponse;
				
			case strtolower($ANSWER_NO):
				$this->conversationStorage->deleteConversation();
				return new DirectedOutgoingMessage(
					$this->user,
					new OutgoingMessage('Фух, а то я уже испугался')
				);
			
			default:
				$command = $this->conversationStorage->getLastMessage()->getCoreCommand();
				if(
					$this->user->isMuted() === false					&&
					$command !== null									&&
					$command->getId() === \CommandSubstitutor\CoreCommandMap::Mute
				){
					return $this->toggleMute();
				}

				$this->repeatQuestion();

				return new DirectedOutgoingMessage(
					$this->user,
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

		$addShowCoreCommand			= $this->coreCommands[\CommandSubstitutor\CoreCommandMap::AddShow];
		$removeShowCoreCommand		= $this->coreCommands[\CommandSubstitutor\CoreCommandMap::RemoveShow];
		$getMyShowsCoreCommand		= $this->coreCommands[\CommandSubstitutor\CoreCommandMap::GetMyShows];
		$muteCoreCommand			= $this->coreCommands[\CommandSubstitutor\CoreCommandMap::Mute];
		$cancelCoreCommand			= $this->coreCommands[\CommandSubstitutor\CoreCommandMap::Cancel];
		$helpCoreCommand			= $this->coreCommands[\CommandSubstitutor\CoreCommandMap::Help];
		$aboutTorCoreCommand		= $this->coreCommands[\CommandSubstitutor\CoreCommandMap::AboutTor];
		$stopCoreCommand			= $this->coreCommands[\CommandSubstitutor\CoreCommandMap::Stop];
		$getShareButtonCoreCommand	= $this->coreCommands[\CommandSubstitutor\CoreCommandMap::GetShareButton];
		$donateCoreCommand			= $this->coreCommands[\CommandSubstitutor\CoreCommandMap::Donate];
		
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
			"$aboutTorCoreCommand - Как обойти блокировку LostFilm.tv"				.PHP_EOL.
			"$donateCoreCommand - Задонатить пару баксов на доширак создателю"		.PHP_EOL.
			"$getShareButtonCoreCommand - Поделиться контактом бота"				.PHP_EOL.
			"$stopCoreCommand - Удалиться из контакт-листа бота"					.PHP_EOL
																					.PHP_EOL.
			'Telegram/VK создателя: @libertypaul'									.PHP_EOL.
			'Ну и электропочта есть, куда ж без неё: admin@libertypaul.ru'			.PHP_EOL.
			'Исходники бота есть на GitHub: '											.
			'https://github.com/LibertyPaul/LostFilmUpdatesBot'						.PHP_EOL
																					.PHP_EOL.
			'Создатель бота не имеет никакого отношеня к проекту LostFilm.tv.';
		
		return new DirectedOutgoingMessage(
			$this->user,
			new OutgoingMessage($helpText)
		);
	}

	private function showAboutTor(){
		$this->conversationStorage->deleteConversation();

		$aboutTor =
			'<i>Disclaimer: Это не реклама, TorProject - некоммерческая организация.</i>'		.PHP_EOL.
			''																					.PHP_EOL.
			'Для обхода блокировки LostFilm (да и вообще чего угодно) '									.
			'можно воспользоваться <b>TorBrowser</b>: https://www.torproject.org/ru/download/'	.PHP_EOL.
			'TorBrowser - это модифицированный Firefox, все подключения которого автоматически '		.
			'направляются через сеть Tor, обеспечивая анонимность и обход блокировок.'			.PHP_EOL.
			''																					.PHP_EOL.
			'Подробнее о проекте: https://ru.wikipedia.org/wiki/Tor'							.PHP_EOL
		;

		return new DirectedOutgoingMessage(
			$this->user,
			new OutgoingMessage(
				$aboutTor,
				new MarkupType(MarkupTypeEnum::HTML)
			)
		);
	}
	
	private function showUserShows(){
		$this->conversationStorage->deleteConversation();

		$userShows = $this->showsAccess->getUserShows($this->user->getId());
		if(count($userShows) === 0){
			$addShowCoreCommand = $this->coreCommands[\CommandSubstitutor\CoreCommandMap::AddShow];
			return new DirectedOutgoingMessage(
				$this->user,
				new OutgoingMessage("Пока тут пусто. Добавь парочку командой $addShowCoreCommand")
			);
		}

		$hasOutdated = false;

		$rows = array();

		foreach($userShows as $show){
			if($show->isOnAir()){
				$icon = '•';
			}
			else{
				$icon = '✕';
				$hasOutdated = true;
			}

			$rows[] = sprintf('%s %s', $icon, $show->getFullTitle()).PHP_EOL.PHP_EOL;
		}

		if($hasOutdated){
			$rows[] = '<i>• - сериал выходит</i>'.PHP_EOL;
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

		return new DirectedOutgoingMessage($this->user, $outgoingMessage);
	}
	
	private function toggleMute(){
		$this->conversationStorage->deleteConversation();

		$this->user->toggleMuted();
		$this->usersAccess->updateUser($this->user);
		
		if($this->user->isMuted()){
			$action = 'Выключил';
		}
		else{
			$action = 'Включил';
		}
		
		return new DirectedOutgoingMessage(
			$this->user,
			new OutgoingMessage("$action все уведомления")
		);
	}
	
	private function insertOrDeleteShow($showAction){
		switch($this->conversationStorage->getConversationSize()){
		# Show all available options
		case 1:
			$shows = $this->showsAccess->getEligibleShows($this->user->getId(), $showAction);
			
			if(count($shows) === 0){
				$this->conversationStorage->deleteConversation();
				
				switch($showAction){
				case \DAL\ShowAction::Add:
				case \DAL\ShowAction::AddTentative:
					$text =
						'Ты подписан на все сериалы.'.PHP_EOL.
						'И как ты успеваешь их все смотреть??';
					
					break;

				case \DAL\ShowAction::Remove:
					$addShowCoreCommand = $this->coreCommands[\CommandSubstitutor\CoreCommandMap::AddShow];
					$text = "Нечего удалять. Для начала добавь пару сериалов командой [$addShowCoreCommand].";
					
					break;
				}
				
				return new DirectedOutgoingMessage(
					$this->user,
					new OutgoingMessage($text)
				);
			}
			else{
				$text = 'Как называется сериал?'.PHP_EOL.
						'Выбери из списка / введи пару слов из названия или '.
						'продиктуй их в голосовом сообщении';
				
				$showTitles = array();
				foreach($shows as $show){
					$showTitles[] = $show->getFullTitle();
				}

				array_unshift($showTitles, '/cancel');
				array_push($showTitles, '/cancel');
				
				return new DirectedOutgoingMessage(
					$this->user,
					new OutgoingMessage(
						$text,
						new MarkupType(MarkupTypeEnum::NoMarkup),
						false,
						$showTitles
					)
				);
			}
			break;

		# Search, add or propose narrow list
		case 2:
			$messageText = $this->conversationStorage->getLastMessage()->getText();
			$show = $this->showsAccess->getEligibleShowByTitle($this->user->getId(), $messageText, $showAction);
			
			# TODO: Merge the below if-else into a singular logic.
			if($show !== null){
				# An exact match was found.
				$this->conversationStorage->deleteConversation();
				
				$track = new \DAL\Track($this->user->getId(), $show->getId());

				switch($showAction){
					case \DAL\ShowAction::Add:
					case \DAL\ShowAction::AddTentative:
						$successText = 'добавлен';
						$this->tracksAccess->addTrack($track);
						
						break;

					case \DAL\ShowAction::Remove:
						$successText = 'удален';
						$this->tracksAccess->deleteTrack($track);
						break;
				}

				$messageText = sprintf("%s %s", $show->getFullTitle(), $successText);
				
				return new DirectedOutgoingMessage(
					$this->user,
					new OutgoingMessage($messageText)
				);
			}
			else{
				# An exact match was not found. Going to guess...
				$matchedShows = $this->showsAccess->getEligibleShowsWithScore(
					$this->user->getId(),
					$this->conversationStorage->getLastMessage()->getText(),
					$showAction
				);

				switch(count($matchedShows)){
				case 0:
					$this->conversationStorage->deleteConversation();

					switch($showAction){
						case \DAL\ShowAction::Add:
						case \DAL\ShowAction::Remove:
							$notFoundText = 'Не найдено подходящих названий.';
							break;

						case \DAL\ShowAction::AddTentative:
							$notFoundText = 'Не найдено подходящих названий. Жми на /add_show чтобы посмотреть в списке.';
							break;
					}

					return new DirectedOutgoingMessage($this->user, new OutgoingMessage($notFoundText));
								
				case 1:
					$this->conversationStorage->deleteConversation();
					$matchedShow = $matchedShows[0];

					$track = new \DAL\Track($this->user->getId(), $matchedShow->getId());

					switch($showAction){
						case \DAL\ShowAction::Add:
						case \DAL\ShowAction::AddTentative:
							$successText = 'добавлен';
							$this->tracksAccess->addTrack($track);
							
							break;

						case \DAL\ShowAction::Remove:
							$successText = 'удален';
							$this->tracksAccess->deleteTrack($track);
							break;
					}

					$messageText = sprintf("%s %s", $mathedShow->getFullTitle(), $successText);
					
					return new DirectedOutgoingMessage(
						$this->user,
						new OutgoingMessage($messageText)
					);
				
				default:
					$showTitles = array();
					foreach($matchedShows as $matchedShow){
						$showTitles[] = $matchedShow->getFullTitle();
					}

					array_unshift($showTitles, '/cancel');
					array_push($showTitles, '/cancel');

					$this->repeatQuestion();
					
					return new DirectedOutgoingMessage(
						$this->user,
						new OutgoingMessage(
							'Какой из этих ты имеешь в виду:',
							new MarkupType(MarkupTypeEnum::NoMarkup),
							false,
							$showTitles
						)
					);
				}
			}
		}
	}

	private function getShareButton(){
		$this->conversationStorage->deleteConversation();

		$shareButton = new InlineOption('Поделиться', InlineOptionType::ShareButton, '');

		return new DirectedOutgoingMessage(
			$this->user,
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

		$YandexMoneyURL = $this->config->getValue('Donate', 'Yandex.Money');
		if($YandexMoneyURL !== null){
			$YandexMoneyButton = new InlineOption(
				'Яндекс.Деньги / Visa / Mastercard',
				InlineOptionType::ExternalLink,
				$YandexMoneyURL
			);
		}

		$PayPalURL = $this->config->getValue('Donate', 'PayPal');
		if($PayPalURL !== null){
			$PayPalButton = new InlineOption(
				'PayPal / Visa / Mastercard / American Express / и т.д.',
				InlineOptionType::ExternalLink,
				$PayPalURL
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
			$this->user,
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
		$excludeMutedStr = $this->conversationStorage->getMessage(5)->getText();

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

		switch($excludeMutedStr){
			case 'Да':
				$excludeMuted = true;
				break;

			case 'Нет':
				$excludeMuted = false;
				break;

			default:
				return array(
					'success' => false,
					'why' => "Exclude Muted=[$excludeMutedStr]"
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
			'message' => $message,
			'excludeMuted' => $excludeMuted
		);
	}
	
	private function broadcast(){
		$allowedUserId = $this->config->getValue('Broadcast', 'Allowed User Id');
		$allowedUserIdInt = intval($allowedUserId);
		if($allowedUserId === null || $this->user->getId() !== $allowedUserIdInt){
			$this->conversationStorage->deleteConversation();

			return new DirectedOutgoingMessage(
				$this->user,
				new OutgoingMessage('Z@TTР3LL|3Н0')
			);
		}

		switch($this->conversationStorage->getConversationSize()){
		case 1:
			return new DirectedOutgoingMessage(
				$this->user,
				new OutgoingMessage('Окей, что раcсылать?')
			);

			break;

		case 2:
			return new DirectedOutgoingMessage(
				$this->user,
				new OutgoingMessage(
					'Пуш уведомление?',
					new MarkupType(MarkupTypeEnum::NoMarkup),
					false,
					array('Да', 'Нет', '/cancel')
				)
			);

		case 3:
			return new DirectedOutgoingMessage(
				$this->user,
				new OutgoingMessage(
					'Будет ли разметка?',
					new MarkupType(MarkupTypeEnum::NoMarkup),
					false,
					array('HTML', 'Telegram API Markup', 'Без разметки', '/cancel')
				)
			);

		case 4:
			return new DirectedOutgoingMessage(
				$this->user,
				new OutgoingMessage(
					'Превью ссылок?',
					new MarkupType(MarkupTypeEnum::NoMarkup),
					false,
					array('Да', 'Нет', '/cancel')
				)
			);
			
		case 5:
			return new DirectedOutgoingMessage(
				$this->user,
				new OutgoingMessage(
					'Потревожить замьюченных?',
					new MarkupType(MarkupTypeEnum::NoMarkup),
					false,
					array('Да', 'Нет', '/cancel')
				)
			);
			
		case 6:
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

				$response = new DirectedOutgoingMessage($this->user, $example);
				return $response;
			}
			else{
				$this->conversationStorage->deleteConversation();
				return new DirectedOutgoingMessage(
					$this->user,
					new OutgoingMessage('Ты накосячил!. '.$result['why'])
				);
			}

			break;

		case 7:
			$result = $this->buildBroadcastMessage();
			if($result['success'] !== true){
				throw new \RuntimeException('Failed to build the broadcast message.');
			}

			$confirmation = $this->conversationStorage->getLastMessage()->getText();

			$this->conversationStorage->deleteConversation();

			if($confirmation !== 'Да'){
				return new DirectedOutgoingMessage(
					$this->user,
					new OutgoingMessage('Рассылка отменена.')
				);
			}

			$started = new DirectedOutgoingMessage(
				$this->user,
				new OutgoingMessage('Начал рассылку.')
			);

			$message = $result['message'];
			$activeUsers = $this->usersAccess->getActiveUsers($result['excludeMuted']);
			$count = 0;

			$broadcastChain = null;

			foreach($activeUsers as $user){
				$current = new DirectedOutgoingMessage($user, $message);
				$current->appendMessage($broadcastChain);
				$broadcastChain = $current;
				++$count;
			}

			$confirmMessage = new DirectedOutgoingMessage(
				$this->user,
				new OutgoingMessage(sprintf('Отправил %d сообщений(е/я).', $count))
			);

			$broadcastChain->appendMessage($confirmMessage);

			return $broadcastChain;
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
				
				case \CommandSubstitutor\CoreCommandMap::AboutTor:
					return $this->showAboutTor();

				case \CommandSubstitutor\CoreCommandMap::Mute:
					return $this->toggleMute();
				
				case \CommandSubstitutor\CoreCommandMap::GetMyShows:
					return $this->showUserShows();
					
				case \CommandSubstitutor\CoreCommandMap::AddShow:
					return $this->insertOrDeleteShow(\DAL\ShowAction::Add);
				
				case \CommandSubstitutor\CoreCommandMap::RemoveShow:
					# TODO: Telegram rejects a keyboard with all shows.
					# Need an alternative way to promps a choice.
					return $this->insertOrDeleteShow(\DAL\ShowAction::Remove);

				case \CommandSubstitutor\CoreCommandMap::AddShowTentative:
					return $this->insertOrDeleteShow(\DAL\ShowAction::AddTentative);

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
				$this->user,
				new OutgoingMessage('Произошла ошибка, я сообщу об этом создателю.')
			);
		}
	}
}
		
		


