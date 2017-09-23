<?php

namespace TelegramAPI;

require_once(__DIR__.'/../core/UserCommand.php');
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

class UpdateHandler{
	private $tracer;
	private $pdo;
	private $speechRecognizer;
	private $telegramAPI;
	private $botan;
	
	public function __construct(){
		$this->tracer = new \Tracer(__CLASS__);
		
		try{
			$this->pdo = \BotPDO::getInstance();
		}
		catch(\Exception $ex){
			$this->tracer->logException('[ERROR]', __FILE__, __LINE__, $ex);
			throw $ex;
		}

		try{
			$config = new \Config($this->pdo);
			$HTTPrequesterFactory = new \HTTPRequesterFactory($config);
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
				$this->tracer->logWarning(
					'[o]', __FILE__, __LINE__, 
					'Botan is enabled but no API key was found.'
				);
				
				if($botanAPIKey !== null){
					$this->botan = new \Botan($botanAPIKey);
				}
			}

		}
		catch(\Exception $ex){
			$this->tracer->logException('[ERROR]', __FILE__, __LINE__, $ex);
			$this->speechRecognizer = null;
			$this->telegramAPI = null;
		}
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

	private static function extractUserInfo($message){
		$chat = $message->chat;

		return array(
			'username'		=> isset($chat->username)	? $chat->username	: null,
			'first_name' 	=> isset($chat->first_name)	? $chat->first_name	: null,
			'last_name' 	=> isset($chat->last_name)	? $chat->last_name	: null
		);
	}

	private function getUserId($telegram_id){
		$getUserIdQuery = $this->pdo->prepare("
			SELECT `telegramUserData`.`user_id`
			FROM `telegramUserData`
			JOIN `users` ON `users`.`id` = `telegramUserData`.`user_id`
			WHERE `users`.`deleted` = 'N'
			AND `telegramUserData`.`telegram_id` = :telegram_id
			FOR UPDATE
		");

		$getUserIdQuery->execute(
			array(
				':telegram_id' => $telegram_id
			)
		);

		$result = $getUserIdQuery->fetch();

		if($result === false){
			return null; # not registred yet
		}
		else{
			return intval($result[0]);
		}
	}

	private function createUser($telegram_id, $username, $first_name, $last_name){
		$createUserQuery = $this->pdo->prepare("
			INSERT INTO `users` (`API`)
			VALUES ('TelegramAPI')
		");
		
		$createUserDataQuery = $this->pdo->prepare('
			INSERT INTO `telegramUserData` (
				`user_id`,
				`telegram_id`,
				`username`,
				`first_name`,
				`last_name`
			)
			VALUES (:user_id, :telegram_id, :username, :first_name, :last_name)
		');

		$res = $this->pdo->beginTransaction();
		if($res === false){
			$this->tracer->logError(
				'[PDO-MySQL]', __FILE__, __LINE__,
				'PDO beginTransaction has faied'
			);
		}

		try{
			$createUserQuery->execute();

			$user_id = intval($this->pdo->lastInsertId());

			$createUserDataQuery->execute(
				array(
					':user_id'		=> $user_id,
					':username'		=> $username,
					':first_name'	=> $first_name,
					':last_name'	=> $last_name,
					':telegram_id'	=> $telegram_id
				)
			);
		}
		catch(\PDOException $ex){
			$this->tracer->logException('[DB]', __FILE__, __LINE__, $ex);

			$res = $this->pdo->rollBack();
			if($res === false){
				$this->tracer->logError(
					'[PDO-MySQL]', __FILE__, __LINE__,
					'PDO rollback has faied'
				);
			}

			throw $ex;
		}
		
		$res = $this->pdo->commit();
		if($res === false){
			$this->tracer->logError('[PDO-MySQL]', __FILE__, __LINE__, 'PDO commit has faied');
		}

		return $user_id;
	}

	private function updateUser($telegram_id, $username, $first_name, $last_name){
		$updateUserDataQuery = $this->pdo->prepare('
			UPDATE `telegramUserData`
			SET	`username`		= :username,
				`first_name`	= :first_name,
				`last_name`		= :last_name
			WHERE `telegram_id`	= :telegram_id
		');

		try{
			$updateUserDataQuery->execute(
				array(
					':username'		=> $username,
					':first_name'	=> $first_name,
					':last_name'	=> $last_name,
					':telegram_id'	=> $telegram_id
				)
			);
		}
		catch(\PDOException $ex){
			$this->tracer->logException('[DB]', __FILE__, __LINE__, $ex);
			throw $ex;
		}
	}
				

	private function createOrUpdateUser($chat){
		$telegram_id = $chat->id;
		$username = isset($chat->username) ? $chat->username : null;
		$first_name = isset($chat->first_name) ? $chat->first_name : null;
		$last_name = isset($chat->last_name) ? $chat->first_name : null;

		$user_id = $this->getUserId($telegram_id);
		
		if($user_id === null){
			$user_id = $this->createUser($telegram_id, $username, $first_name, $last_name);
		}
		else{
			$this->updateUser($telegram_id, $username, $first_name, $last_name);
		}

		return $user_id;
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

	private function mapUserCommand($text){
		$result = null;

		switch($text){
			case '/start':
				$result = new \core\UserCommand(\core\UserCommandMap::Start);
				break;
			case '/add_show':
				$result = new \core\UserCommand(\core\UserCommandMap::AddShow);
				break;
			case '/remove_show':
				$result = new \core\UserCommand(\core\UserCommandMap::RemoveShow);
				break;
			case '/get_my_shows':
				$result = new \core\UserCommand(\core\UserCommandMap::GetMyShows);
				break;
			case '/mute':
				$result = new \core\UserCommand(\core\UserCommandMap::Mute);
				break;
			case '/cancel':
				$result = new \core\UserCommand(\core\UserCommandMap::Cancel);
				break;
			case '/help':
				$result = new \core\UserCommand(\core\UserCommandMap::Help);
				break;
			case '/stop':
				$result = new \core\UserCommand(\core\UserCommandMap::Stop);
				break;
			case '/share':
				$result = new \core\UserCommand(\core\UserCommandMap::GetShareButton);
				break;
			case '/donate':
				$result = new \core\UserCommand(\core\UserCommandMap::Donate);
				break;
			case '/broadcast':
				$result = new \core\UserCommand(\core\UserCommandMap::Broadcast);
				break;
		}

		return $result;
	}

	public function handleUpdate($update){
		$update = self::normalizeUpdateFields($update);

		$user_id = $this->createOrUpdateUser($update->message->chat);

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
			catch(\Exception $ex){
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
		$command = $this->mapUserCommand($rawCommand);

		$incomingMessage = new \core\IncomingMessage(
			$user_id,
			$command,
			$text,
			$update->update_id
		);

		$coreHandler = new \core\UpdateHandler();
		$coreHandler->processIncomingMessage($incomingMessage);

		if($command !== null){
			try{
				$this->sendToBotan($update->message, $rawCommand);
			}
			catch(\Exception $ex){
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










