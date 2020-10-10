<?php

namespace core;

require_once(__DIR__.'/../lib/Config.php');
require_once(__DIR__.'/ConversationStorage.php');
require_once(__DIR__.'/BotPDO.php');
require_once(__DIR__.'/OutgoingMessage.php');
require_once(__DIR__.'/IncomingMessage.php');
require_once(__DIR__.'/MessageRouterFactory.php');
require_once(__DIR__.'/UserController.php');

require_once(__DIR__.'/../lib/Tracer/TracerFactory.php');
require_once(__DIR__.'/../TelegramAPI/TelegramAPI.php');

require_once(__DIR__.'/../lib/DAL/MessagesHistory/MessagesHistoryAccess.php');
require_once(__DIR__.'/../lib/DAL/MessagesHistory/MessageHistory.php');
require_once(__DIR__.'/../lib/DAL/Users/UsersAccess.php');
require_once(__DIR__.'/../lib/DAL/Users/User.php');

class UpdateHandler{
	private $tracer;
	private $messageRouter;

	private $messagesHistoryAccess;
	private $usersAccess;

	public function __construct(\PDO $pdo){
		$this->tracer = \TracerFactory::getTracer(__CLASS__, $pdo);

		$this->messageRouter = MessageRouterFactory::getInstance();
		$this->messagesHistoryAccess = new \DAL\MessagesHistoryAccess($pdo);
		$this->usersAccess = new \DAL\UsersAccess($pdo);
	}

	private function logIncomingMessage(int $user_id, IncomingMessage $incomingMessage){
		try{
			$externalId = $incomingMessage->getAPISpecificData()->getUniqueMessageId();

			$messageHistory = new \DAL\MessageHistory(
				null,
				new \DateTimeImmutable(),
				'User',
				$user_id,
				$externalId,
				$incomingMessage->getText(),
				null,
				null
			);

			$loggedMessageId = $this->messagesHistoryAccess->addMessageHistory($messageHistory);

		}
		catch(\DAL\MessagesHistoryDuplicateExternalIdException $ex){
			$this->tracer->logfError(
				'[DB ERROR]', __FILE__, __LINE__,
				'Unable to log the mesasge due to duplicate external_id: [%d]',
				$externalId
			);

			$this->tracer->logDebug(
				'[DB ERROR]', __FILE__, __LINE__, PHP_EOL.
				$incomingMessage
			);
			
			$conflictingMessage = $this->messagesHistoryAccess->getByUpdateId($externalId);
			$this->tracer->logfDebug(
				'[o]', __FILE__, __LINE__,
				'MessagesHistory ID was substituted to existing one [%d]',
				$conflictingMessage->getId()
			);

			return $conflictingMessage->getId();
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

	public function processIncomingMessage(\DAL\User $user, IncomingMessage $incomingMessage){
		$this->tracer->logDebug(
			'[o]', __FILE__, __LINE__,
			'Entered processIncomingMessage with message:'.PHP_EOL.
			$incomingMessage
		);

		$loggedRequestId = $this->logIncomingMessage($user->getId(), $incomingMessage);

		$this->tracer->logDebug(
			'[o]', __FILE__, __LINE__,
			"IncomingMessage was logged with id=[$loggedRequestId]"
		);

		$userController = new UserController($user);

		$this->tracer->logDebug('[o]', __FILE__, __LINE__, 'Processing message ...');

		$response = $userController->processMessage($incomingMessage);

		$this->tracer->logDebug('[o]', __FILE__, __LINE__, 'Processing has finished.');

		while($response !== null){
			try{
				$route = $this->messageRouter->getRoute($response->getUser());

				$this->tracer->logDebug(
					'[o]', __FILE__, __LINE__,
					'Message was successfully routed. Sending ...'
				);
			
				$result = $route->send($response->getOutgoingMessage());

				assert(count($result) > 1);

				switch($result[0]){
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

