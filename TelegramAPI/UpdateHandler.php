<?php

namespace TelegramAPI;

require_once(__DIR__.'/../core/IncomingMessage.php');
require_once(__DIR__.'/../core/UpdateHandler.php');
require_once(__DIR__.'/../core/BotPDO.php');
require_once(__DIR__.'/../lib/Tracer/TracerFactory.php');
require_once(__DIR__.'/../lib/stuff.php');
require_once(__DIR__.'/../lib/Config.php');
require_once(__DIR__.'/../lib/HTTPRequester/HTTPRequesterFactory.php');
require_once(__DIR__.'/../lib/SpeechRecognizer/SpeechRecognizer.php');
require_once(__DIR__.'/TelegramAPI.php');
require_once(__DIR__.'/TelegramSpecificData.php');
require_once(__DIR__.'/../lib/CommandSubstitutor/CommandSubstitutor.php');
require_once(__DIR__.'/../lib/DAL/Users/UsersAccess.php');
require_once(__DIR__.'/DAL/TelegramUserDataAccess/TelegramUserDataAccess.php');
require_once(__DIR__.'/DAL/TelegramUserDataAccess/TelegramUserData.php');

class UpdateHandler{
	private $tracer;
	private $pdo;
	private $speechRecognizer;
	private $commandSubstitutor;
	private $usersAccess;
	private $telegramUserDataAccess;
	private $telegramAPI;
	private $coreHandler;
	private $telegramBotName;

	private $forwardingChat;
	private $forwardingSilent;
	private $forwardEverything;
	
	public function __construct(){
		$this->pdo = \BotPDO::getInstance();
		$this->tracer = \TracerFactory::getTracer(__CLASS__, $this->pdo);
		$this->commandSubstitutor = new \CommandSubstitutor\CommandSubstitutor($this->pdo);
		$this->usersAccess = new \DAL\UsersAccess($this->pdo);
		$this->telegramUserDataAccess = new \DAL\TelegramUserDataAccess($this->pdo);

		$config = new \Config($this->pdo);

		try{
			$HTTPrequesterFactory = new \HTTPRequester\HTTPRequesterFactory($config, $this->pdo);
			$HTTPRequester = $HTTPrequesterFactory->getInstance();

			$this->speechRecognizer = new \SpeechRecognizer\SpeechRecognizer(
				$config,
				$HTTPRequester,
				$this->pdo
			);

			$telegramAPIToken = $config->getValue('TelegramAPI', 'token');
			$this->telegramAPI = new TelegramAPI($telegramAPIToken, $HTTPRequester, $this->pdo);
			$this->coreHandler = new \core\UpdateHandler($this->pdo);
		}
		catch(\Throwable $ex){
			$this->tracer->logException('[ERROR]', __FILE__, __LINE__, $ex);
			$this->speechRecognizer = null;
			$this->telegramAPI = null;
		}

		$this->telegramBotName = $config->getValue(
			'TelegramAPI',
			'Bot Name'
		);

		$this->forwardingChat = $config->getValue(
			'TelegramAPI',
			'Forwarding Chat'
		);

		$this->forwardingSilent = $config->getValue(
			'TelegramAPI',
			'Forwarding Silent',
			'Y'
		) === 'Y';

		$this->forwardEverything = $config->getValue(
			'TelegramAPI',
			'Forward Everything',
			'N'
		) === 'Y';
	}

	private static function normalizeUpdateFields($update){
		$result = clone $update;
	
		if(isset($result->update_id)){
			$result->update_id = intval($result->update_id);
		}

		$result->message->from->id = intval($result->message->from->id);
		$result->message->chat->id = intval($result->message->chat->id);

		if(isset($result->message->migrate_from_chat_id)){
			$result->message->migrate_from_chat_id = intval($result->message->migrate_from_chat_id);
		}

		return $result;
	}

	private static function shouldBeForwarded($message){
		return
			isset($message->audio)		||
			isset($message->document)	||
			isset($message->game)		||
			isset($message->photo)		||
			isset($message->sticker)	||
			isset($message->video)		||
			isset($message->video_note)	||
			isset($message->contact)	||
			isset($message->location)	||
			isset($message->venue);
	}

	private function forwardUpdate($update){
		if(
			$this->forwardingChat !== null	&&
			isset($update->message)			&&
			$this->telegramAPI !== null
		){
			try{
				$this->telegramAPI->forwardMessage(
					$this->forwardingChat,
					$update->message->chat->id,
					$update->message->message_id,
					$this->forwardingSilent
				);
			}
			catch(\Throwable $ex){
				$this->tracer->logException(
					'[ATTACHMENT FORWARDING]', __FILE__, __LINE__, 
					$ex
				);
			}
		}
		else{
			$this->tracer->logfWarning(
				'[o]', __FILE__, __LINE__,
				'Unable to forward due to:'					.PHP_EOL.
				'	$this->forwardingChat !== null:	[%d]'	.PHP_EOL.
				'	$this->telegramAPI !== null:	[%d]'	.PHP_EOL.
				'	isset($update->message):		[%d]'	.PHP_EOL,
				$this->forwardingChat !== null,
				$this->telegramAPI !== null,
				isset($update->message)
			);
		}
	}

	private function getUserInfo(int $chat_id){
		$telegramUserDataList = $this->telegramUserDataAccess->getAPIUserDataByChatID($chat_id);

		foreach($telegramUserDataList as $telegramUserData){
			$user = $this->usersAccess->getUserById($telegramUserData->getUserId());

			if($user->isDeleted()){
				continue;
			}

			return array(
				'user' => $user,
				'telegramUserData' => $telegramUserData
			);
		}

		return null;
	}

	private function createUser(
		int $chat_id,
		string $type,
		string $username = null,
		string $first_name,
		string $last_name = null
	){
		try{
			# TODO: Move transaction stuff behind DAL
			$res = $this->pdo->beginTransaction();
			if($res === false){
				$this->tracer->logError(
					'[PDO-MySQL]', __FILE__, __LINE__,
					'PDO beginTransaction has faied'
				);
			}

			$user = new \DAL\User(
				null,
				'TelegramAPI',
				false,
				false,
				new \DateTimeImmutable()
			);

			$user_id = $this->usersAccess->addUser($user);
			$user->setId($user_id);
			$user->setJustRegistred();

			$this->tracer->logfDebug(
				'[o]', __FILE__, __LINE__,
				"Created user:\n%s", $user
			);
			
			$telegramUserData = new \DAL\TelegramUserData(
				$user_id,
				$chat_id,
				$type,
				$username,
				$first_name,
				$last_name
			);

			$this->telegramUserDataAccess->addAPIUserData($telegramUserData);

			$res = $this->pdo->commit();
			if($res === false){
				$this->tracer->logError(
					'[PDO-MySQL]', __FILE__, __LINE__,
					'PDO commit has faied'
				);
			}

			return array(
				'user' => $user,
				'telegramUserData' => $telegramUserData
			);
		}
		catch(\Throwable $ex){
			$this->tracer->logException('[DB]', __FILE__, __LINE__, $ex);

			$res = $this->pdo->rollBack();
			if($res === false){
				throw new \RuntimeException("PDO Rollback failed.", 0, $ex);
			}

			throw $ex;
		}
	}

	private function createOrUpdateUser($message){
		$chat = $message->chat;

		switch($chat->type){
		case 'private':
			$username	= isset($chat->username)	? $chat->username	: null;
			$first_name	= isset($chat->first_name)	? $chat->first_name	: null;
			$last_name	= isset($chat->last_name)	? $chat->last_name	: null;
			$oldChatID	= null;
			break;

		case 'group':
		case 'supergroup':
			$username	= null;
			$first_name	= isset($chat->title) ? $chat->title : "Group $chatID";
			$last_name	= null;
			$oldChatID	= isset($message->migrate_from_chat_id)	? $message->migrate_from_chat_id : null;
			break;

		default:
			throw new \RuntimeException("Unsupported chat type.");
		}

		if($oldChatID === null){
			$currentChatID = $chat->id;
			$newChatID = null;
		}
		else{
			$currentChatID = $oldChatID;
			$newChatID = $chat->id;
		}

		$userInfo = $this->getUserInfo($currentChatID);

		if($userInfo === null){
			if($newChatID !== null){
				$currentChatID = $newChatID;
			}

			$userInfo = $this->createUser($currentChatID, $chat->type, $username, $first_name, $last_name);
		}
		else{
			if($newChatID !== null){
				$userInfo['telegramUserData']->setAPISpecificId($newChatID);
			}
			
			$userInfo['telegramUserData']->setType($chat->type);

			$this->telegramUserDataAccess->updateAPIUserData($userInfo['telegramUserData']);
		}

		return $userInfo;
	}

	private function recognizeVoiceMessage($voice){
		if($this->speechRecognizer === null){
			$this->tracer->logError(
				'[SPEECH RECOGNITION]', __FILE__, __LINE__,
				'SpeechRecognizer was not initialized.'
			);

			return null;
		}

		if($this->telegramAPI === null){
			$this->tracer->logError(
				'[SPEECH RECOGNITION]', __FILE__, __LINE__,
				'TelegramAPI was not initialized.'
			);

			return null;
		}

		if($voice->duration > 15){
			return null; #TODO: properly explain max voice length to the user
		}

		assert(strpos($voice->mime_type, 'ogg') !== -1);
			
		$voiceBinary = $this->telegramAPI->downloadFile($voice->file_id);
		$voiceBase64 = base64_encode($voiceBinary);

		$possibleVariants = $this->speechRecognizer->recognize($voiceBase64, 'ogg');
		if(count($possibleVariants) < 1){
			return null;
		}

		$topOptions = array_keys($possibleVariants, max($possibleVariants));

		return $topOptions[0];
	}

	private function extractUserCommand(string $rawText = null){
		if($rawText === null){
			return array(
				'text' => null,
				'botName' => null
			);
		}

		$text = trim($rawText);
		if(empty($text)){
			return array(
				'text' => '',
				'botName' => null
			);
		}

		if($text[0] !== '/'){
			return array(
				'text' => $text,
				'botName' => null
			);
		}

		$botName = null;

		$spacePos = strpos($text, ' ');
		if($spacePos !== false){
			$text = substr($text, 0, $spacePos);
		}

		$atPos = strpos($text, '@');
		if($atPos !== false){
			$botName = substr($text, $atPos + 1);
			$text = substr($text, 0, $atPos);
		}

		return array(
			'text'		=> $text,
			'botName'	=> $botName
		);
	}

	private function isAddressedToMe(string $chatType, $message, string $botName = null){
		$res = false;

		switch($chatType){
		case 'private':
			$res = true;
			break;

		case 'group':
		case 'supergroup':
			if(
				isset($message->reply_to_message) &&
				isset($message->reply_to_message->from) &&
				isset($message->reply_to_message->from->username)
			){
				if($this->telegramBotName === $message->reply_to_message->from->username){
					$res = true;
				}
			}

			if($this->telegramBotName === $botName){
				$res = true;
			}

			break;

		default:
			throw new \LogicException("Unknown Telegram chat type:".PHP_EOL.$telegramUserData);
		}

		if($res === false){
			$this->tracer->logfDebug(
				'[o]', __FILE__, __LINE__,
				"Message is not addressed to me [%s][%s][%s]\n%s",
				$chatType,
				$botName,
				$this->telegramBotName,
				print_r($message, true)
			);
		}

		return $res;
	}

	public function handleUpdate($update){
		$update = self::normalizeUpdateFields($update);
		
		if($this->forwardEverything	|| self::shouldBeForwarded($update->message)){
			$this->tracer->logDebug(
				'[ATTACHMENT FORWARDING]', __FILE__, __LINE__,
				'Message is eligible for forwarding.'
			);
			
			$this->forwardUpdate($update);
		}
		
		try{
			$userInfo = $this->createOrUpdateUser($update->message);
		}
		catch(\Throwable $ex){
			$this->tracer->logException('[o]', __FILE__, __LINE__, $ex);
			throw $ex;
		}

		if(isset($update->message->text)){
			$this->tracer->logDebug(
				'[o]', __FILE__, __LINE__,
				'Message->text is present'
			);
			
			$text = $update->message->text;
		}
		elseif(isset($update->message->voice)){
			if($userInfo['telegramUserData']->getType() !== 'private'){
				$this->tracer->logDebug(
					'[o]', __FILE__, __LINE__,
					'Voice messages are not supported in groups'
				);

				throw new \RuntimeException("Voice messages are not supported in groups");
			}

			$this->tracer->logDebug(
				'[o]', __FILE__, __LINE__,
				'Message->text is absent, but voice is present. Recognizing...'
			);
			
			try{
				$text = $this->recognizeVoiceMessage($update->message->voice);
			}
			catch(\Throwable $ex){
				$this->tracer->logException('[o]', __FILE__, __LINE__, $ex);
				throw $ex;
			}

			$this->tracer->logDebug(
				'[o]', __FILE__, __LINE__,
				"SpeechRecognition result: '$text'"
			);
		}
		elseif(isset($update->message->migrate_from_chat_id)){
			$this->tracer->logDebug(
				'[o]', __FILE__, __LINE__,
				'Chat was converted.'
			);

			return;
		}
		else{
			$this->tracer->logDebug(
				'[o]', __FILE__, __LINE__,
				'Both message->text and message->voice are absent. Aborting.'
			);

			throw new \RuntimeException('Both message->text and message->voice are absent');
		}


		$userCommand = $this->extractUserCommand($text);

		$commandText = $userCommand['text'];
		$botName = $userCommand['botName'];

		$addressedToMe = $this->isAddressedToMe(
			$userInfo['telegramUserData']->getType(),
			$update->message,
			$botName
		);

		if($addressedToMe === false){
			return;
		}

		$command = $this->commandSubstitutor->convertAPIToCore('TelegramAPI', $commandText);

		if($command !== null){
			$originalText = $commandText;
			$commandText = $command->getText();

			$this->tracer->logfDebug(
				'[COMMAND]', __FILE__, __LINE__,
				'User command [%s] was mapped to [%s]', $originalText, $commandText
			);
		}

		$telegramSpecificData = new TelegramSpecificData(
			$update->message->message_id,
			$update->update_id,
			null
		);

		$incomingMessage = new \core\IncomingMessage(
			$command,
			$text,
			$telegramSpecificData
		);

		$this->coreHandler->processIncomingMessage($userInfo['user'], $incomingMessage);

	}

}
