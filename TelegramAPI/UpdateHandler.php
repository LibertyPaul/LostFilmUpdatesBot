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
require_once(__DIR__.'/../lib/Botan.php');
require_once(__DIR__.'/TelegramAPI.php');
require_once(__DIR__.'/../lib/CommandSubstitutor/CommandSubstitutor.php');
require_once(__DIR__.'/../lib/DAL/Users/UsersAccess.php');
require_once(__DIR__.'/DAL/TelegramUserData/TelegramUserDataAccess.php');
require_once(__DIR__.'/DAL/TelegramUserData/TelegramUserData.php');

class UpdateHandler{
	private $tracer;
	private $pdo;
	private $speechRecognizer;
	private $commandSubstitutor;
	private $usersAccess;
	private $telegramAPI;
	private $botan;
	
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
		$this->usersAccess = new \DAL\UsersAcess($this->pdo);
		$this->telegramUserDataAccess = new \DAL\TelegramUserDataAccess($this->pdo);

		try{
			$config = new \Config($this->pdo);
			$HTTPrequesterFactory = new \HTTPRequester\HTTPRequesterFactory($config);
			$HTTPRequester = $HTTPrequesterFactory->getInstance();

			$this->speechRecognizer = new \SpeechRecognizer\SpeechRecognizer(
				$config,
				$HTTPRequester
			);

			$telegramAPIToken = $config->getValue('TelegramAPI', 'token');
			$this->telegramAPI = new TelegramAPI($telegramAPIToken, $HTTPRequester);

			$this->botan = null;
			$botanEnabled = $config->getValue('Botan', 'Enabled');

			if($botanEnabled === 'Y'){
				$botanAPIKey = $config->getValue('Botan', 'API Key');

				if($botanAPIKey === null){
					$this->tracer->logWarning(
						'[o]', __FILE__, __LINE__, 
						'Botan is enabled but no API key was found.'
					);
				}
				else{
					$this->botan = new \Botan($botanAPIKey);
				}
			}

		}
		catch(\Throwable $ex){
			$this->tracer->logException('[ERROR]', __FILE__, __LINE__, $ex);
			$this->speechRecognizer = null;
			$this->telegramAPI = null;
		}
	}

	private static function validateUpdate($update){
		return
			$update !== null				&&
			isset($update->message) 		&&
			isset($update->message->chat)	&&
			isset($update->message->from);
	}

	private static function normalizeUpdateFields($update){
		$result = clone $update;
	
		if(isset($result->update_id)){
			$result->update_id = intval($result->update_id);
		}
		$result->message->from->id = intval($result->message->from->id);
		$result->message->chat->id = intval($result->message->chat->id);

		return $result;
	}

	private function getUserInfo(int $telegram_id){
		$telegramUserData = $this->telegramUserDataAccess->getAPIUserDataByTelegramId($telegram_id);
		if($telegramUserData === null){
			return null;
		}

		$user = $this->usersAccess->getUserById($telegramUserData->getUserId());
		if($user === null){
			return null;
		}

		if($user->isDeleted()){
			return null;
		}

		return array(
			'user' => $user,
			'telegramUserData' => $telegramUserData
		);
	}

	private function createUser(int $telegram_id, string $username = null, string $first_name, string $last_name = null){
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
			
			$telegramUserData = new \DAL\TelegramUserData(
				$user_id,
				$telegram_id,
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

	private function createOrUpdateUser($chat){
		$telegram_id = $chat->id;
		$username = isset($chat->username) ? $chat->username : null;
		$first_name = isset($chat->first_name) ? $chat->first_name : null;
		$last_name = isset($chat->last_name) ? $chat->first_name : null;

		$userInfo = $this->getUserInfo($telegram_id);
		
		if($userInfo === null){
			$userInfo = $this->createUser($telegram_id, $username, $first_name, $last_name);
		}
		else{
			$telegramUserData = new \DAL\TelegramUserData(
				$userInfo['user']->getId(),
				$telegram_id,
				$username,
				$first_name,
				$last_name
			);

			$telegramUserDataAccess->updateAPIUserData($telegramUserData);
			$userInfo['telegramUserData'] = $telegramUserData;
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

	private function extractUserCommand($rawText){
		$text = trim($rawText);
		if(empty($text)){
			return $rawText;
		}

		if($text[0] !== '/'){
			return $rawText;
		}

		$atPos = strpos($text, '@');
		if($atPos !== false){
			$text = substr($text, 0, $atPos);
		}

		$spacePos = strpos($text, ' ');
		if($spacePos !== false){
			$text = substr($text, 0, $spacePos);
		}

		return $text;
	}

	public function handleUpdate($update){
		if(self::validateUpdate($update) === false){
			$this->tracer->logError(
				'[INVALID UPDATE]', __FILE__, __LINE__,
				'Update is invalid:'.PHP_EOL.
				print_r($update, true)
			);

			throw new \RuntimeException('Invalid update');
		}

		$update = self::normalizeUpdateFields($update);

		$userInfo = $this->createOrUpdateUser($update->message->chat);

		if(isset($update->message->text)){
			$this->tracer->logDebug(
				'[o]', __FILE__, __LINE__,
				'Message->text is present'
			);
			$text = $update->message->text;
		}
		elseif(isset($update->message->voice)){
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

		$rawCommand = $this->extractUserCommand($text);
		$command = $this->commandSubstitutor->convertAPIToCore('TelegramAPI', $rawCommand);

		$commandText = $rawCommand;
		if($command !== null){
			$commandText = $command->getText();
		}

		$this->tracer->logDebug(
			'[COMMAND]', __FILE__, __LINE__,
			sprintf('User command [%s] was mapped to [%s]', $rawCommand, $commandText)
		);

		$incomingMessage = new \core\IncomingMessage(
			$command,
			$text,
			$update->update_id
		);

		$coreHandler = new \core\UpdateHandler();
		$coreHandler->processIncomingMessage($userInfo['user']->getId(), $incomingMessage);

		# TODO: Remove Botan
		if($command !== null){
			try{
				$this->sendToBotan($update->message, $rawCommand);
			}
			catch(\Throwable $ex){
				$this->tracer->logException('[o]', __FILE__, __LINE__, $ex);
			}
		}
	}

	private function sendToBotan($message, $event){
		if($this->botan === null){
			return;
		}

		$messageJSON = json_encode($message);
		assert($messageJSON !== false);

		$message_assoc = json_decode($messageJSON, true);
		assert($message_assoc !== null);

		$this->botan->track($message_assoc, $event);
	}

}










