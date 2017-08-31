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

		$this->botan = null;
		$botanEnabled = $this->config->getValue('Botan', 'Enabled');

		if($botanEnabled === 'Y'){
			$botanAPIKey = $this->config->getValue('Botan', 'API Key');
			$this->tracer->logWarning(
				'[o]', __FILE__, __LINE__, 
				'Botan is enabled but no API key was found.'
			);
			
			if($botanAPIKey !== null){
				$this->botan = new \Botan($botanAPIKey);
			}
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

	private function logIncomingMessage(IncomingMessage $incomingMessage){
		try{
			$this->logRequestQuery->execute(
				array(
					':user_id'		=> $incomingMessage->getUserId(),
					':update_id'	=> $incomingMessage->getUpdateId(),
					':text'			=> $incomingMessage->getText()
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
		catch(\PDOException $ex){
			$this->tracer->logException('[DB ERROR]', __FILE__, __LINE__, $ex);
			$this->tracer->logError(
				'[DB ERROR]', __FILE__, __LINE__, PHP_EOL.
				$incomingMessage
			);
			
			if($ex->errorInfo[1] === 1062){# Duplicate entry error code
				throw new DuplicateUpdateException();
			}
			else{
				throw new \RuntimeException('logRequestQuery call has failed');
			}
		}
		
		return $loggedMessageId;
	}

	private function logOutgoingMessage(
		DirectedOutgoingMessage $outgoingMessage,
		$loggedRequestId,
		$statusCode
	){
		while($outgoingMessage !== null){
			$text = substr($outgoingMessage->getOutgoingMessage()->getText(), 0, 5000);
			try{
				$this->logResponseQuery->execute(
					array(
						':user_id'		=> $outgoingMessage->getUserId(),
						':text'			=> $text,
						':inResponseTo'	=> $loggedRequestId,
						':statusCode'	=> $statusCode
					)
				);
			}
			catch(\PDOException $ex){
				$this->tracer->logException('[DB ERROR]', __FILE__, __LINE__, $ex);
				$this->tracer->logError(
					'[DB ERROR]', __FILE__, __LINE__,
					$outgoingMessage
				);
				
				if($ex->errorInfo[1] === 1062){# Duplicate entry error code
					throw new DuplicateUpdateException();
				}
				else{
					throw new \RuntimeException('logRequestQuery call has failed');
				}
			}
			finally{
				$outgoingMessage = $outgoingMessage->nextMessage();
			}
		}
	}

	public function processIncomingMessage(IncomingMessage $incomingMessage){
		$this->tracer->logDebug(
			'[o]', __FILE__, __LINE__,
			'Entered processIncomingMessage with message:'.PHP_EOL.
			$incomingMessage
		);

		$loggedRequestId = $this->logIncomingMessage($incomingMessage);

		$this->tracer->logDebug(
			'[o]', __FILE__, __LINE__,
			"IncomingMessage was logged with id=[$loggedRequestId]"
		);

		try{
			$conversationStorage = new ConversationStorage($incomingMessage->getUserId());
			$conversationStorage->insertMessage($incomingMessage);
			$initialCommand = $conversationStorage->getFirstMessage()->getUserCommand();
		}
		catch(\Exception $ex){
			$this->tracer->logError(
				'[o]', __FILE__, __LINE__,
				'Conversation Storage Error'
			);

			$this->tracer->logException('[o]', __FILE__, __LINE__, $ex);
			throw $ex;
		}

		try{
			$userController = new UserController(
				$incomingMessage->getUserId(),
				$conversationStorage
			);
		}
		catch(\Exception $ex){
			$this->tracer->logError(
				'[o]', __FILE__, __LINE__,
				'UserController creation error'
			);

			$this->tracer->logException('[o]', __FILE__, __LINE__, $ex);
			throw $ex;
		}

		$this->tracer->logDebug('[o]', __FILE__, __LINE__, 'Processing message ...');

		$directedOutgoingMessage = $userController->processLastUpdate();

		$this->tracer->logDebug('[o]', __FILE__, __LINE__, 'Processing has finished.');

		while($directedOutgoingMessage !== null){
			try{
				$this->tracer->logDebug(
					'[o]', __FILE__, __LINE__,
					'Routing message:'.PHP_EOL.
					$directedOutgoingMessage
				);

				$route = $this->messageRouter->route(
					$directedOutgoingMessage->getUserId()
				);

				$this->tracer->logDebug(
					'[o]', __FILE__, __LINE__,
					'Message was successfully routed.'
				);

				$this->tracer->logDebug('[o]', __FILE__, __LINE__, 'Sending ...');
			
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

				$this->tracer->logDebug(
					'[o]', __FILE__, __LINE__,
					"Sending status: [$statusCode]"
				);
				
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
		
		if($initialCommand !== null){
			$this->sendToBotan($incomingMessage, $initialCommand);
		}

		return $loggedRequestId;
	}
}

















