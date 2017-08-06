<?php

namespace TelegramAPI;

require_once(__DIR__.'/../core/IncomingMessage.php');
require_once(__DIR__.'/../core/UpdateHandler.php');
require_once(__DIR__.'/../lib/Tracer/Tracer.php');
require_once(__DIR__.'/../lib/stuff.php');
require_once(__DIR__.'/../lib/Config.php');
require_once(__DIR__.'/../core/BotPDO.php');
require_once(__DIR__.'/TelegramAPI.php');

class UpdateHandler{
	private $tracer;
	private $telegramAPI;
	private $pdo;
	
	public function __construct(TelegramAPI $telegramAPI){
		assert($telegramAPI !== null);
		$this->telegramAPI = $telegramAPI;
		
		$this->tracer = new \Tracer(__CLASS__);
		
		try{
			$this->pdo = \BotPDO::getInstance();
		}
		catch(\Exception $ex){
			$this->tracer->logException('[ERROR]', __FILE__, __LINE__, $ex);
			throw $ex;
		}
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

	private static function normalizeUpdateFields($update){
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
			$this->tracer->logError(
				'[ERROR]',
				__FILE__,
				__LINE__,
				'preg_match error: '.preg_last_error()
			);
			throw new LogicException('preg_match error: '.preg_last_error());
		}
		if($res === 0){
			$this->tracer->logError('[ERROR]', __FILE__, __LINE__, "Invalid command '$text'");
			return $text;
		}

		return $matches[1];
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
			catch(\Exception $ex){
				$this->tracer->logException('[TELEGRAM API]', __FILE__, __LINE__, $ex);
			}
		}
	}

	private function getUserId($telegram_id){
		$isAlreadyRegistredQuery = $this->pdo->prepare("
			SELECT id
			FROM `users`
			WHERE `API` = 'TelegramAPI'
			AND	`APIIdentifier` = :telegram_id
		");

		$isAlreadyRegistredQuery->execute(
			array(
				':telegram_id' => $telegram_id
			)
		);

		$result = $isAlreadyRegistredQuery->fetch();

		if($result === false){
			return null; # not registred yet
		}
		else{
			return intval($result[0]);
		}
	}

	private function createUser($telegram_id, $username, $first_name, $last_name){
		$createUserQuery = $this->pdo->prepare("
			INSERT INTO `users` (`API`, `APIIdentifier`)
			VALUES ('TelegramAPI', :telegram_id)
		");
		
		$createUserDataQuery = $this->pdo->prepare('
			INSERT INTO `telegramUserData` (`telegram_id`, `username`, `first_name`, `last_name`)
			VALUES (:telegram_id, :username, :first_name, :last_name)
		');

		$res = $this->pdo->beginTransaction();
		if($res === false){
			$this->tracer->logError(
				'[PDO-MySQL]',
				__FILE__,
				__LINE__,
				'PDO beginTransaction has faied'
			);
		}

		try{
			$createUserQuery->execute(
				array(
					':telegram_id' => $telegram_id
				)
			);

			$user_id = intval($this->pdo->lastInsertId());

			$createUserDataQuery->execute(
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

			$res = $this->pdo->rollBack();
			if($res === false){
				$this->tracer->logError(
					'[PDO-MySQL]',
					__FILE__,
					__LINE__,
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
				

	private function createOrUpdateUser($from){
		$telegram_id = $from->id;
		$username = isset($from->username) ? $from->username : null;
		$first_name = isset($from->first_name) ? $from->first_name : null;
		$last_name = isset($from->last_name) ? $from->first_name : null;

		$user_id = $this->getUserId($telegram_id);
		
		if($user_id === null){
			$user_id = $this->createUser($telegram_id, $username, $first_name, $last_name);
		}
		else{
			$this->updateUser($telegram_id, $username, $first_name, $last_name);
		}

		return $user_id;
	}

	public function handleUpdate($update){
		if(self::validateFields($update) === false){
			$this->tracer->logError(
				'[DATA ERROR]', __FILE__, __LINE__,
				'Update is invalid:'.PHP_EOL.print_r($update, true)
			);
			throw new \RuntimeException('Invalid update');
		}

		$update = self::normalizeUpdateFields($update);

		$user_id = $this->createOrUpdateUser($update->message->from);

		$coreHandler = new \core\UpdateHandler();
		$incomingMessage = new \core\IncomingMessage(
			$user_id,
			$update->message->text,
			$update->message,
			$update->update_id
		);

		$coreHandler->processIncomingMessage($incomingMessage);
	}

}










