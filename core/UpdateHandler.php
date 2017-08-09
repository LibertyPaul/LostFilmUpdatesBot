<?php

namespace core;

require_once(__DIR__.'/../lib/Config.php');
require_once(__DIR__.'/../lib/Botan.php');
require_once(__DIR__.'/ConversationStorage.php');
require_once(__DIR__.'/BotPDO.php');
require_once(__DIR__.'/OutgoingMessage.php');
require_once(__DIR__.'/IncomingMessage.php');
require_once(__DIR__.'/MessageRouterFactory.php');
require_once(__DIR__.'/UserController.php');

require_once(__DIR__.'/../lib/Tracer/Tracer.php');
require_once(__DIR__.'/../TelegramAPI/TelegramAPI.php');
require_once(__DIR__.'/../lib/HTTPRequester/HTTPRequesterFactory.php');

class DuplicateUpdateException extends \RuntimeException{}

class UpdateHandler{
	private $tracer;
	private $botan;
	private $pdo;
	private $config;
	private $messageRouter;

	# Queries
	private $logRequestQuery;
	private $logResponseQuery;

	public function __construct(){
		$this->pdo = \BotPDO::getInstance();
		$this->config = new \Config($this->pdo);

		$this->tracer = new \Tracer(__CLASS__);

		$this->messageRouter = MessageRouterFactory::getInstance();

		$botanAPIKey = $this->config->getValue('Botan', 'API Key');
		if($botanAPIKey !== null){
			$this->botan = new \Botan($botanAPIKey);
		}
		else{
			$this->botan = null;
		}

		$this->logRequestQuery = $this->pdo->prepare("
			INSERT INTO `messagesHistory` (
				source,
				user_id,
				update_id,
				text
			)
			VALUES (
				'User',
				:user_id,
				:update_id,
				:text
			)
		");

		$this->logResponseQuery = $this->pdo->prepare("
			INSERT INTO `messagesHistory` (
				source,
				user_id,
				text,
				inResponseTo,
				statusCode
			)
			VALUES (
				'UpdateHandler',
				:user_id,
				:text,
				:inResponseTo,
				:statusCode
			)
		");
	}

	private function sendToBotan(IncomingMessage $message, $event){
		if($this->botan === null){
			return;
		}
	
		$message_assoc = json_decode($message->getRawMessage());
		$this->botan->track($message_assoc, $event);
	}

	private function logIncomingMessage(IncomingMessage $message){
		try{
			$this->logRequestQuery->execute(
				array(
					':user_id'		=> $message->getUserId(),
					':update_id'	=> $message->getUpdateId(),
					':text'			=> $message->getText()
				)
			);

			$loggedMessageId = intval($this->pdo->lastInsertId());
			if($this->pdo->errorCode() === 'IM001'){
				$this->tracer->logError(
					'[PDO]', __FILE__, __LINE__,
					'PDO was unable to get lastInsertId'
				);
				$loggedMessageId = null;
			}
		}
		catch(PDOException $ex){
			$this->tracer->logException('[DB ERROR]', __FILE__, __LINE__, $ex);
			$this->tracer->logError(
				'[DB ERROR]', __FILE__, __LINE__,
				PHP_EOL.print_r($message, true)
			);

			throw new DuplicateUpdateException(); # TODO: check for DB error code
		}

		return $loggedMessageId;
	}

	private function logOutgoingMessage(
		DirectedOutgoingMessage $message,
		$loggedRequestId,
		$statusCode
	){
		while($message !== null){
			try{
				$this->logResponseQuery->execute( # TODO: check message length
					array(
						':user_id'		=> $message->getUserId(),
						':text'			=> $message->getOutgoingMessage()->getText(),
						':inResponseTo'	=> $loggedRequestId,
						':statusCode'	=> $statusCode
					)
				);

				$message = $message->nextMessage();
			}
			catch(PDOException $ex){
				$this->tracer->logException('[DB ERROR]', __FILE__, __LINE__, $ex);
				$this->tracer->logError(
					'[DB ERROR]', __FILE__, __LINE__,
					PHP_EOL.print_r($message, true)
				);
			}
		}
	}

		

	public function processIncomingMessage(IncomingMessage $message){
		$this->tracer->logDebug(
			'[o]', __FILE__, __LINE__,
			'Entered processIncomingMessage with message:'.PHP_EOL.
			print_r($message, true)
		);

		$loggedRequestId = $this->logIncomingMessage($message);

		$this->tracer->logDebug(
			'[o]', __FILE__, __LINE__,
			"IncomingMessage was logged with id=[$loggedRequestId]"
		);

		try{
			$conversationStorage = new ConversationStorage($message->getUserId());
			$conversationStorage->insertMessage($message->getText());
			$initialCommand = $conversationStorage->getFirstMessage();
		}
		catch(Exception $ex){
			$this->tracer->logError(
				'[o]', __FILE__, __LINE__,
				'Conversation Storage Error'
			);

			$this->tracer->logException('[o]', __FILE__, __LINE__, $ex);

			throw $ex;
		}

		try{
			$userController = new UserController(
				$message->getUserId(),
				$conversationStorage
			);
		}
		catch(Exception $ex){
			$this->tracer->logError(
				'[o]', __FILE__, __LINE__,
				'UserController creation error'
			);

			$this->tracer->logException('[o]', __FILE__, __LINE__, $ex);
			throw $ex;
		}

		$directedOutgoingMessage = $userController->processLastUpdate();

		while($directedOutgoingMessage !== null){
			try{
				$route = $this->messageRouter->route(
					$directedOutgoingMessage->getUserId()
				);
			
				$result = $route->send($directedOutgoingMessage->getOutgoingMessage());

				switch($result){
					case SendResult::Success:
						$statusCode = 0;
						break;
	
					case SendResult::Fail:
					default:
						$statusCode = 1;
						break;
				}
	
					
				$this->logOutgoingMessage(
					$directedOutgoingMessage,
					$loggedRequestId,
					$statusCode
				);
			}
			catch(\Exception $ex){
				$this->tracer->logException('[o]', __FILE__, __LINE__, $ex);
			}
			
			$directedOutgoingMessage = $directedOutgoingMessage->nextMessage();
		}
			
		$this->sendToBotan($message, $initialCommand);
	}
}

















