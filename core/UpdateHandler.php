<?php

namespace core;

require_once(__DIR__.'/../lib/Config.php');
require_once(__DIR__.'/ConversationStorage.php');
require_once(__DIR__.'/BotPDO.php');
require_once(__DIR__.'/OutgoingMessage.php');
require_once(__DIR__.'/IncomingMessage.php');
require_once(__DIR__.'/MessageRouterFactory.php');
require_once(__DIR__.'/UserController.php');

require_once(__DIR__.'/../lib/Tracer/Tracer.php');
require_once(__DIR__.'/../TelegramAPI/TelegramAPI.php');

require_once(__DIR__.'/../lib/DAL/MessagesHistory/MessagesHistoryAccess.php');
require_once(__DIR__.'/../lib/DAL/MessagesHistory/MessageHistory.php');
require_once(__DIR__.'/../lib/DAL/Users/UsersAccess.php');
require_once(__DIR__.'/../lib/DAL/Users/User.php');


class DuplicateUpdateException extends \RuntimeException{}

class UpdateHandler{
	private $tracer;
	private $config;
	private $messageRouter;

	private $messagesHistoryAccess;
	private $usersAccess;

	public function __construct(){
		$pdo = \BotPDO::getInstance();
		$this->config = new \Config($pdo);

		$this->tracer = new \Tracer(__CLASS__);

		$this->messageRouter = MessageRouterFactory::getInstance();
		$this->messagesHistoryAccess = new \DAL\MessagesHistoryAccess($this->tracer, $pdo);
		$this->usersAccess = new \DAL\UsersAccess($this->tracer, $pdo);
	}

	private function logIncomingMessage(int $user_id, IncomingMessage $incomingMessage){
		try{
			$messageHistory = new \DAL\MessageHistory(
				null,
				new \DateTimeImmutable(),
				'User',
				$user_id,
				$incomingMessage->getUpdateId(),
				$incomingMessage->getText(),
				null,
				null
			);

			$loggedMessageId = $this->messagesHistoryAccess->addMessageHistory($messageHistory);

		}
		catch(\Throwable $ex){
			$this->tracer->logException('[DB ERROR]', __FILE__, __LINE__, $ex);
			$this->tracer->logDebug(
				'[DB ERROR]', __FILE__, __LINE__, PHP_EOL.
				$incomingMessage
			);

			throw $ex;
		}
		
		return $loggedMessageId;
	}

	private function logOutgoingMessage(
		DirectedOutgoingMessage $outgoingMessage,
		int $loggedRequestId,
		int $statusCode
	){
		try{
			$messageHistory = new \DAL\MessageHistory(
				null,
				new \DateTimeImmutable(),
				'UpdateHandler',
				$outgoingMessage->getUser()->getId(),
				null,
				$outgoingMessage->getOutgoingMessage()->getText(),
				$loggedRequestId,
				$statusCode
			);

			$this->messagesHistoryAccess->addMessageHistory($messageHistory);

		}
		catch(\PDOException $ex){
			$this->tracer->logException('[DB ERROR]', __FILE__, __LINE__, $ex);
			$this->tracer->logDebug('[DB ERROR]', __FILE__, __LINE__, PHP_EOL.$messageHistory);
			throw $ex;
		}
	}

	public function processIncomingMessage(int $user_id, IncomingMessage $incomingMessage){
		$this->tracer->logDebug(
			'[o]', __FILE__, __LINE__,
			'Entered processIncomingMessage with message:'.PHP_EOL.
			$incomingMessage
		);

		$loggedRequestId = $this->logIncomingMessage($user_id, $incomingMessage);

		$this->tracer->logDebug(
			'[o]', __FILE__, __LINE__,
			"IncomingMessage was logged with id=[$loggedRequestId]"
		);

		try{
			$user = $this->usersAccess->getUserById($user_id);
			if($user->isDeleted()){
				throw new \LogicException("Incoming update from a deleted user [$user_id]");
			}

			$userController = new UserController($user);
		}
		catch(\Throwable $ex){
			$this->tracer->logException('[o]', __FILE__, __LINE__, $ex);
			throw $ex;
		}

		$this->tracer->logDebug('[o]', __FILE__, __LINE__, 'Processing message ...');

		$response = $userController->processMessage($incomingMessage);

		$this->tracer->logDebug('[o]', __FILE__, __LINE__, 'Processing has finished.');

		while($response !== null){
			try{
				$route = $this->messageRouter->route($response->getUser());

				$this->tracer->logDebug(
					'[o]', __FILE__, __LINE__,
					'Message was successfully routed. Sending ...'
				);
			
				$result = $route->send($response->getOutgoingMessage());

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
					$response,
					$loggedRequestId,
					$statusCode
				);
			}
			catch(\Throwable $ex){
				$this->tracer->logException('[o]', __FILE__, __LINE__, $ex);
			}
			
			$response = $response->nextMessage();
		}

		return $loggedRequestId;
	}
}

