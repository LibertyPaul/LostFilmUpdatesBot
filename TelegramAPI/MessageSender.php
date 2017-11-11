<?php

namespace TelegramAPI;

require_once(__DIR__.'/../core/MessageSenderInterface.php');
require_once(__DIR__.'/../core/OutgoingMessage.php');
require_once(__DIR__.'/../lib/Tracer/Tracer.php');
require_once(__DIR__.'/../lib/Config.php');
require_once(__DIR__.'/TelegramAPI.php');
require_once(__DIR__.'/../core/BotPDO.php');

class MessageSender implements \core\MessageSenderInterface{
	private $tracer;
	private $outgoingMessagesTracer;
	private $telegramAPI;
	private $getTelegramIdQuery;
	private $sleepOn429CodeMs;

	public function __construct(TelegramAPI $telegramAPI){
		$this->telegramAPI = $telegramAPI;

		$this->tracer = new \Tracer(__CLASS__);
		$this->outgoingMessagesTracer = new \Tracer('OutgoingMessages');

		$pdo = \BotPDO::getInstance();

		$config = new \Config($pdo);
		$this->sleepOn429CodeMs = $config->getValue(
			'Telegram API',
			'Sleep On 429 Code ms',
			500
		);

		$this->maxSendAttempts = $config->getValue('Telegram API', 'Max Send Attempts', 5);

		$this->getTelegramIdQuery = $pdo->prepare('
			SELECT `telegram_id`
			FROM `telegramUserData`
			WHERE `user_id` = :user_id
		');
	}

	private function getTelegramId($user_id){
		assert(is_int($user_id));

		$this->getTelegramIdQuery->execute(
			array(
				':user_id' => $user_id
			)
		);

		$res = $this->getTelegramIdQuery->fetch();
		
		if($res === false){
			throw new \RuntimeException("Telegram Id was not found for user_id=[$used_id]");
		}

		return intval($res[0]);
	}

	public function send($user_id, \core\OutgoingMessage $message){
		$telegram_id = $this->getTelegramId($user_id);

		$sendResult = \core\SendResult::Success;

		while($message !== null){
			$this->outgoingMessagesTracer->logEvent(
				'[o]', __FILE__, __LINE__,
				"Message to user_id=[$user_id], telegram_id=[$telegram_id]".
				PHP_EOL.$message
			);

			$attempt = 0;
			
			for($attempt = 0; $attempt < $this->maxSendAttempts; ++$attempt){
				$result = $this->telegramAPI->send(
					$telegram_id,
					$message->getText(),
					$message->markupType(),
					$message->URLExpandEnabled(),
					$message->getResponseOptions(),
					$message->getInlineOptions()
				);

				if($result->getCode() === 429){
					$this->tracer->logWarning(
						'[TELEGRAM API]', __FILE__, __LINE__,
						"Got 429 HTTP Response. Nap for {$this->sleepOn429CodeMs} ms."
					);
					usleep($this->sleepOn429CodeMs);
				}
				else{
					break;
				}
			}

			$this->outgoingMessagesTracer->logEvent(
				'[o]', __FILE__, __LINE__,
				'Returned code: '.$result->getCode()
			);

			if($result->getCode() >= 400){
				$sendResult = \core\SendResult::Fail;
			}

			$message = $message->nextMessage();
		}

		return $sendResult;
	}
}
	
