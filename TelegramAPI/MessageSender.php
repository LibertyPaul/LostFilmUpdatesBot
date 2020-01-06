<?php

namespace TelegramAPI;

require_once(__DIR__.'/../core/MessageSenderInterface.php');
require_once(__DIR__.'/../core/OutgoingMessage.php');
require_once(__DIR__.'/../lib/Tracer/Tracer.php');
require_once(__DIR__.'/../lib/Config.php');
require_once(__DIR__.'/TelegramAPI.php');
require_once(__DIR__.'/../core/BotPDO.php');
require_once(__DIR__.'/../lib/CommandSubstitutor/CommandSubstitutor.php');

require_once(__DIR__.'/DAL/TelegramUserData/TelegramUserDataAccess.php');
require_once(__DIR__.'/DAL/TelegramUserData/TelegramUserData.php');

class MessageSender implements \core\MessageSenderInterface{
	private $telegramAPI;
	private $tracer;
	private $outgoingMessagesTracer;
	private $commandSubstitutor;
	private $telegramUserDataAccess;
	private $sleepOn429CodeMs;
	private $maxSendAttempts;
	private $forwardingChat;
	private $forwardingSilent;
	private $forwardEverything;

	public function __construct(TelegramAPI $telegramAPI){
		$this->telegramAPI = $telegramAPI;

		$this->tracer = new \Tracer(__CLASS__);
		$this->outgoingMessagesTracer = new \Tracer(__NAMESPACE__.'.OutgoingMessages');

		$pdo = \BotPDO::getInstance();
		$this->commandSubstitutor = new \CommandSubstitutor\CommandSubstitutor($pdo);

		$this->telegramUserDataAccess = new \DAL\TelegramUserDataAccess($pdo);

		$config = new \Config($pdo);
		$this->sleepOn429CodeMs = $config->getValue(
			'Telegram API',
			'Sleep On 429 Code ms',
			500
		);

		$this->maxSendAttempts = $config->getValue(
			'Telegram API',
			'Max Send Attempts',
			5
		);

		$this->forwardingChat = $config->getValue(
			'TelegramAPI',
			'Forwarding Chat'
		);

		$this->forwardingSilent = $config->getValue(
			'TelegramAPI',
			'Forwarding Silent',
			'Y'
		) === 'Y';

		$this->forwardEverything = $config->getValue(
			'TelegramAPI',
			'Forward Everything',
			'N'
		) === 'Y';
	}

	public function send(int $user_id, \core\OutgoingMessage $message){
		$telegramUserData = $this->telegramUserDataAccess->getAPIUserDataByUserId($user_id);

		$sendResult = \core\SendResult::Success;

		while($message !== null){
			$this->outgoingMessagesTracer->logfEvent(
				'[o]', __FILE__, __LINE__,
				"Message to user_id=[%d], telegram_id=[%d]".PHP_EOL.
				"%s",
				$telegramUserData->getUserId(),
				$telegramUserData->getTelegramId(),
				$message
			);

			$messageText = $this->commandSubstitutor->replaceCoreCommandsInText(
				'TelegramAPI',
				$message->getText()
			);

			$attempt = 0;
			
			for($attempt = 0; $attempt < $this->maxSendAttempts; ++$attempt){
				$result = $this->telegramAPI->send(
					$telegramUserData->getTelegramId(),
					$messageText,
					$message->markupType(),
					$message->URLExpandEnabled(),
					$message->getResponseOptions(),
					$message->getInlineOptions()
				);

				if($result->getCode() === 429){
					$this->tracer->logfWarning(
						'[TELEGRAM API]', __FILE__, __LINE__,
						"Got 429 HTTP Response. Nap for [%d] ms.",
						$this->sleepOn429CodeMs
					);

					usleep($this->sleepOn429CodeMs);
				}
				else{
					break;
				}
			}

			$this->outgoingMessagesTracer->logfEvent(
				'[o]', __FILE__, __LINE__,
				'Returned code: [%d]',
				$result->getCode()
			);

			if($result->getCode() >= 400){
				$sendResult = \core\SendResult::Fail;
			}

			if($sendResult === \core\SendResult::Success){
				$APIResponseJSON = $result->getBody();
				$APIResponse = json_decode($APIResponseJSON);
				if($APIResponse === null){
					$this->tracer->logError(
						'[o]', __FILE__, __LINE__,
						'Failed to parse API response:'
					);

					$this->tracer->logDebug('[o]', __FILE__, __LINE__, PHP_EOL.$APIResponseJSON);
				}
				else{
					$messageId = intval($APIResponse->result->message_id);
					$this->forwardIfApplicable($telegramUserData->getTelegramId(), $messageId);
				}
			}

			$message = $message->nextMessage();
		}

		return $sendResult;
	}

	private function forwardIfApplicable(int $userChatId, int $messageId){
		if($this->forwardEverything){
			$this->tracer->logDebug(
				'[ATTACHMENT FORWARDING]', __FILE__, __LINE__,
				'Message is eligible for forwarding.'
			);

			if(
				$this->forwardingChat !== null	&&
				$this->telegramAPI !== null
			){
				try{
					$this->telegramAPI->forwardMessage(
						$this->forwardingChat,
						$userChatId,
						$messageId,
						$this->forwardingSilent
					);
				}
				catch(\Throwable $ex){
					$this->tracer->logException(
						'[ATTACHMENT FORWARDING]', __FILE__, __LINE__, 
						$ex
					);
				}
			}
			else{
				$this->tracer->logfWarning(
					'[o]', __FILE__, __LINE__,
					'Unable to forward due to:'					.PHP_EOL.
					'	$this->forwardingChat !== null:	[%d]'	.PHP_EOL.
					'	$this->telegramAPI !== null:	[%d]'	.PHP_EOL.
					'	isset($update->message):		[%d]'	.PHP_EOL,
					$this->forwardingChat !== null,
					$this->telegramAPI !== null
				);
			}
		}
	}
}
	
