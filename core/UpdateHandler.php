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
require_once(__DIR__.'/../lib/DAL/Users/User.php');

class UpdateHandler{
	private $tracer;
	private $messageRouter;

	private $messagesHistoryAccess;

	public function __construct(\PDO $pdo){
		$this->tracer = \TracerFactory::getTracer(__CLASS__, $pdo);

		$this->messageRouter = MessageRouterFactory::getInstance();
		$this->messagesHistoryAccess = new \DAL\MessagesHistoryAccess($pdo);
	}

	private function logIncomingMessage(int $user_id, IncomingMessage $incomingMessage){
        $externalId = null;

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
				'Unable to log the message due to duplicate external_id: [%d]',
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
		?int $inResponseTo,
		int $statusCode,
		?int $externalId
	){
        $messageHistory = new \DAL\MessageHistory(
            null,
            new \DateTimeImmutable(),
            'UpdateHandler',
            $outgoingMessage->getUser()->getId(),
            #$externalId, TODO: Rework external id handling
            null,
            $outgoingMessage->getOutgoingMessage()->getText(),
            $inResponseTo,
            $statusCode
        );

		try{
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

		$res = $this->sendMessages($response, $loggedRequestId);
		if($res !== 0){
			$this->tracer->logfError('[o]', __FILE__, __LINE__, '[%d] messages delivery failed.', $res);
		}

		return $loggedRequestId;
	}

	public function sendMessages(DirectedOutgoingMessage $message, int $inResponseTo = null): int {
		$failures = 0;

		while($message !== null){
			try{
				$failures += 1;

				$route = $this->messageRouter->getRoute($message->getUser());

				$this->tracer->logDebug(
					'[o]', __FILE__, __LINE__,
					'Message was successfully routed. Sending ...'
				);
			
				$result = $route->send($message->getOutgoingMessage());

				$this->tracer->logfDebug(
					'[o]', __FILE__, __LINE__,
					"Sending result: [%s]",
					SendResult::toString($result->getSendResult())
				);

				$this->logOutgoingMessage(
					$message,
					$inResponseTo,
					$result->getSendResult(),
					$result->getExternalId()
				);

				if($result->getSendResult() === SendResult::Success){
					$failures -= 1;
				}
			}
			catch(\Throwable $ex){
				$this->tracer->logException('[o]', __FILE__, __LINE__, $ex);
			}
			
			$message = $message->nextMessage();
		}

		return $failures;
	}
}

