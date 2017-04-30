<?php
require_once(__DIR__.'/UserController.php');
require_once(__DIR__.'/ConversationStorage.php');
require_once(__DIR__.'/../lib/Tracer/Tracer.php');
require_once(__DIR__.'/../lib/stuff.php');
require_once(__DIR__.'/../lib/Config.php');
require_once(__DIR__.'/../Botan/Botan.php');
require_once(__DIR__.'/BotPDO.php');
require_once(__DIR__.'/TelegramAPI.php');

class DuplicateUpdateException extends RuntimeException{}

class UpdateHandler{
	private $tracer;
	private $memcache;
	private $conversationStorageKeyPrefix;
	private $lastUpdateIdKey;
	private $telegramAPI;
	private $botan;
	private $pdo;
	private $logRequestQuery;
	private $requestDBId;
	private $logResponseQuery;

	public function __construct(TelegramAPI $telegramAPI){
		assert($telegramAPI !== null);
		$this->telegramAPI = $telegramAPI;
		
		$this->tracer = new Tracer(__CLASS__);
		
		$this->pdo = null;

		try{
			$this->memcache = Stuff\createMemcache();
			$this->pdo = BotPDO::getInstance();
		}
		catch(Exception $ex){
			$this->tracer->logException('[ERROR]', __FILE__, __LINE__, $ex);
			throw $ex;
		}

		$config = new Config($this->pdo);
		$botanAPIKey = $config->getValue('Botan', 'API Key');
		if($botanAPIKey !== null){
			$this->botan = new Botan($botanAPIKey);
		}
		else{
			$this->botan = null;
		}

		$this->conversationStorageKeyPrefix = $config->getValue('Conversation Storage', 'Key Prefix');
		if($this->conversationStorageKeyPrefix === null){
			$this->tracer->logWarning('[CONFIG]', __FILE__, __LINE__, 'Conversation Storage / Key Prefix is not set. This bot may overwrite other bot\'s conversations');
			$this->conversationStorageKeyPrefix = '';
		}

		$this->logRequestQuery = $this->pdo->prepare("
			INSERT INTO `messagesHistory` (source, chat_id, update_id, text)
			VALUES ('User', :chat_id, :update_id, :text)
		");

		$this->logResponseQuery = $this->pdo->prepare("
			INSERT INTO `messagesHistory` (source, chat_id, text, inResponseTo, statusCode)
			VALUES ('UpdateHandler', :chat_id, :text, :inResponseTo, :statusCode)
		");

		$this->requestDBId = null;

	}

	private static function validateFields($update){
		return
			isset($update->message)				&&
			isset($update->message->from)		&&
			isset($update->message->from->id)	&&
			isset($update->message->chat)		&&
			isset($update->message->chat->id)	&&
			isset($update->message->text);
	}

	private static function normalizeFields($update){
		$result = clone $update;
	
		if(isset($result->update_id)){
			$result->update_id = intval($result->update_id);
		}
		$result->message->from->id = intval($result->message->from->id);
		$result->message->chat->id = intval($result->message->chat->id);

		return $result;
	}

	private function extractCommand($text){
		$regex = '/(\/\w+)/';
		$matches = array();
		$res = preg_match($regex, $text, $matches);
		if($res === false){
			$this->tracer->logError('[ERROR]', __FILE__, __LINE__, 'preg_match error: '.preg_last_error());
			throw new LogicException('preg_match error: '.preg_last_error());
		}
		if($res === 0){
			$this->tracer->logError('[ERROR]', __FILE__, __LINE__, "Invalid command '$text'");
			return $text;
		}

		return $matches[1];
	}

	private function verifyData($update){
		return true;
	}

	private function sendToBotan($message, $event){
		$message_assoc = json_decode(json_encode($message), true);
		if($this->botan !== null){
			$this->botan->track($message_assoc, $event);
		}
	}

	private static function extractUserInfo($message){
		$chat = $message->chat;

		return array(
			'username'		=> isset($chat->username)	? $chat->username	: null,
			'first_name' 	=> isset($chat->first_name)	? $chat->first_name	: null,
			'last_name' 	=> isset($chat->last_name)	? $chat->last_name	: null
		);
	}

	private function respond(MessageList $response){
		foreach($response->getMessages() as $message){
			try{
				$this->telegramAPI->sendMessage($message);
			}
			catch(Exception $ex){
				$this->tracer->logException('[TELEGRAM API]', __FILE__, __LINE__, $ex);
			}
		}
	}

	private function handleMessage($message){
		try{
			$conversationStorage = new ConversationStorage(
				$message->from->id,
				$this->conversationStorageKeyPrefix
			);
			
			if($conversationStorage->getConversationSize() === 0){
				$message->text = $this->extractCommand($message->text);
			}
			
			$conversationStorage->insertMessage($message->text);
			
			$userController = new UserController($message->chat->id);
			$response = $userController->incomingUpdate(
				$conversationStorage,
				self::extractUserInfo($message)
			);

			$messageList = new MessageList($response);

			foreach($messageList as $message){
				try{
					$res = $this->telegramAPI->sendMessage($message);
				}
				catch(HTTPException $ex){
					$this->tracer->logException('[HTTP ERROR]', __FILE__, __LINE__, $ex);
					$res = array(
						'code'	=> null,
						'value'	=> null
					);
				}

				$fields = $message->get();

				try{
					$this->logResponseQuery->execute(
						array(
							':chat_id'		=> $fields['chat_id'],
							':text'			=> $fields['text'],
							':inResponseTo'	=> $this->requestDBId,
							':statusCode'	=> $res['code']
						)
					);
				}
				catch(PDOException $ex){
					$this->tracer->logException('[DB]', __FILE__, __LINE__, $ex);
				}
			}
		}
		catch(Exception $ex){
			$this->tracer->logException('[ERROR]', __FILE__, __LINE__, $ex);
		}
		
		if($conversationStorage->getConversationSize() === 1){
			try{
				$this->sendToBotan($message, $conversationStorage->getLastMessage());
			}
			catch(Exception $ex){
				$this->tracer->logException('[BOTAN ERROR]', __FILE__, __LINE__, $ex);
			}
		}
	}

	public function handleUpdate($update){
		if(self::validateFields($update) === false){
			$this->tracer->logError('[DATA ERROR]', __FILE__, __LINE__, 'Update is invalid:'.PHP_EOL.print_r($update, true));
			throw new RuntimeException('Invalid update');
		}

		$update = self::normalizeFields($update);

		if($this->verifyData($update) === false){
			$this->tracer->logError('[ERROR]', __FILE__, __LINE__, 'Invalid update data: Last id: '.$this->getLastUpdateId().PHP_EOL.print_r($update, true));
			throw new RuntimeException('Invalid update data'); // TODO: check if we should gently skip in such case
		}

		try{
			$this->logRequestQuery->execute(
				array(
					':chat_id'		=> $update->message->chat->id,
					':update_id'	=> $update->update_id,
					':text'			=> $update->message->text
				)
			);

			$this->requestDBId = $this->pdo->lastInsertId();
			$this->requestDBId = intval($this->requestDBId);

			if($this->pdo->errorCode() === 'IM001'){
				$this->tracer->logError('[PDO]', __FILE__, __LINE__, 'PDO was unable to get lastInsertId');
				$this->requestDBId = null;
			}
		}
		catch(PDOException $ex){
			$this->tracer->logException('[DB ERROR]', __FILE__, __LINE__, $ex);
			$this->tracer->logError('[DB ERROR]', __FILE__, __LINE__, PHP_EOL.print_r($update, true));
			throw new DuplicateUpdateException();
		}

		$this->handleMessage($update->message);
	}

}










