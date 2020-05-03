<?php

namespace TelegramAPI;

require_once(__DIR__.'/../core/IncomingMessage.php');
require_once(__DIR__.'/../core/UpdateHandler.php');
require_once(__DIR__.'/../core/BotPDO.php');
require_once(__DIR__.'/../lib/Tracer/Tracer.php');
require_once(__DIR__.'/../lib/stuff.php');
require_once(__DIR__.'/../lib/Config.php');
require_once(__DIR__.'/../lib/HTTPRequester/HTTPRequesterFactory.php');
require_once(__DIR__.'/../lib/SpeechRecognizer/SpeechRecognizer.php');
require_once(__DIR__.'/TelegramAPI.php');
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
	private $telegramBotName;
	
	public function __construct(){
		$this->tracer = new \Tracer(__CLASS__);
		
		try{
			$this->pdo = \BotPDO::getInstance();
		}
		catch(\Throwable $ex){
			$this->tracer->logException('[ERROR]', __FILE__, __LINE__, $ex);
			throw $ex;
		}

		$this->commandSubstitutor = new \CommandSubstitutor\CommandSubstitutor($this->pdo);
		$this->usersAccess = new \DAL\UsersAccess($this->tracer, $this->pdo);
		$this->telegramUserDataAccess = new \DAL\TelegramUserDataAccess($this->tracer, $this->pdo);

		$config = new \Config($this->pdo);

		try{
			$HTTPrequesterFactory = new \HTTPRequester\HTTPRequesterFactory($config);
			$HTTPRequester = $HTTPrequesterFactory->getInstance();

			$this->speechRecognizer = new \SpeechRecognizer\SpeechRecognizer(
				$config,
				$HTTPRequester
			);

			$telegramAPIToken = $config->getValue('TelegramAPI', 'token');
			$this->telegramAPI = new TelegramAPI($telegramAPIToken, $HTTPRequester);
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

	private function createUser(int $chat_id, string $type, string $username = null, string $first_name, string $last_name = null){
		try{
			$res = $this->pdo->beginTransaction();
			if($res === false){
				$this->tracer->logError('[PDO-MySQL]', __FILE__, __LINE__, 'PDO beginTransaction has faied');
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

			$this->tracer->logfDebug('[o]', __FILE__, __LINE__, "Created user:\n%s", $user);
			
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
				$this->tracer->logError('[PDO-MySQL]', __FILE__, __LINE__, 'PDO commit has faied');
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
				$this->tracer->logError('[PDO-MySQL]', __FILE__, __LINE__, 'PDO rollback has faied');
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

		$incomingMessage = new \core\IncomingMessage(
			$command,
			$text,
			$update->update_id
		);

		$coreHandler = new \core\UpdateHandler();
		$coreHandler->processIncomingMessage($userInfo['user'], $incomingMessage);

	}

}
