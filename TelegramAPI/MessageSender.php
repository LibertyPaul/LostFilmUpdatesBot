<?php

namespace TelegramAPI;

require_once(__DIR__.'/../core/MessageSenderInterface.php');
require_once(__DIR__.'/../core/OutgoingMessage.php');
require_once(__DIR__.'/../lib/Tracer/TracerFactory.php');
require_once(__DIR__.'/../lib/Config.php');
require_once(__DIR__.'/TelegramAPI.php');
require_once(__DIR__.'/../core/BotPDO.php');
require_once(__DIR__.'/../lib/CommandSubstitutor/CommandSubstitutor.php');

require_once(__DIR__.'/DAL/TelegramUserDataAccess/TelegramUserDataAccess.php');
require_once(__DIR__.'/DAL/TelegramUserDataAccess/TelegramUserData.php');

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
	private $telegramBotName;

	public function __construct(TelegramAPI $telegramAPI){
		$this->telegramAPI = $telegramAPI;

		$pdo = \BotPDO::getInstance();
		$this->tracer = \TracerFactory::getTracer(__CLASS__, $pdo);
		$this->outgoingMessagesTracer = \TracerFactory::getTracer(__NAMESPACE__.'.OutgoingMessages', null, true, false);

		$this->commandSubstitutor = new \CommandSubstitutor\CommandSubstitutor($pdo);

		$this->telegramUserDataAccess = new \DAL\TelegramUserDataAccess($pdo);

		$config = \Config::getConfig($pdo);

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

		$this->telegramBotName = $config->getValue(
			'TelegramAPI',
			'Bot Name'
		);
	}

	public function send(int $user_id, \core\OutgoingMessage $message): array {
		$telegramUserData = $this->telegramUserDataAccess->getAPIUserDataByUserId($user_id);

		if($telegramUserData->getType() === 'private'){
			$commandSubstitutionFormat = "%s";
		}
		else{
			$commandSubstitutionFormat = "%s@".$this->telegramBotName;
		}

		$results = array();

		while($message !== null){
			$this->outgoingMessagesTracer->logfEvent(
				'[o]', __FILE__, __LINE__,
				"Message to user_id=[%d], chat_id=[%d]".PHP_EOL.
				"%s",
				$telegramUserData->getUserId(),
				$telegramUserData->getAPISpecificId(),
				$message
			);

			$messageText = $this->commandSubstitutor->replaceCoreCommands(
				'TelegramAPI',
				$message->getText(),
				$commandSubstitutionFormat
			);

			$responseOptions = $this->commandSubstitutor->replaceCoreCommands(
				'TelegramAPI',
				$message->getResponseOptions()
			);

			$attempt = 0;

			$telegramSpecificData = $message->getRequestAPISpecificData();

			if($telegramUserData->getType() === 'private'){
				$request_message_id = null;
			}
			elseif($telegramSpecificData instanceof TelegramSpecificData){
				$request_message_id = $telegramSpecificData->getMessageId();
			}
			else{
				$this->tracer->logfError(
					'[o]', __FILE__, __LINE__,
					'Request API Specific data is of unknown type: [%s]',
					gettype($telegramSpecificData)
				);

				$request_message_id = null;
			}
			
			for($attempt = 0; $attempt < $this->maxSendAttempts; ++$attempt){
				$result = $this->telegramAPI->send(
					$telegramUserData->getAPISpecificId(),
					$messageText,
					$request_message_id,
					$message->markupType(),
					$message->URLExpandEnabled(),
					$responseOptions,
					$message->getInlineOptions()
				);

				if($result->isErrorTooFrequent()){
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

			if($result->isSuccess()){
				$results[] = \core\SendResult::Success;
				$this->outgoingMessagesTracer->logEvent(
					'[o]', __FILE__, __LINE__,
					'Success.'
				);

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
					$this->forwardIfApplicable($telegramUserData->getAPISpecificId(), $messageId);
				}
			}
			else{
				$results[] = \core\SendResult::Fail;
			}

			$message = $message->nextMessage();
		}

		return $results;
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
	
