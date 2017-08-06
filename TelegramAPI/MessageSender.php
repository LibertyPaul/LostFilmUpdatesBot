<?php

namespace TelegramAPI;

require_once(__DIR__.'/../core/MessageSenderInterface.php');
require_once(__DIR__.'/../core/OutgoingMessage.php');
require_once(__DIR__.'/../lib/Tracer/Tracer.php');
require_once(__DIR__.'/TelegramAPI.php');

class MessageSender implements \core\MessageSenderInterface{
	private $tracer;
	private $outgoingMessagesTracer;
	private $telegramAPI;

	public function __construct(TelegramAPI $telegramAPI){
		assert($telegramAPI !== null);
		$this->telegramAPI = $telegramAPI;

		$this->tracer = new \Tracer(__CLASS__);
		$this->outgoingMessagesTracer = new \Tracer('OutgoingMessages');
	}

	public function send($telegram_id, \core\OutgoingMessage $message){
		if($message === null){
			$this->tracer->logError('[o]', __FILE__, __LINE__, 'OutgoingMessage is null');
			throw new \InvalidArgumentException('OutgoingMessage is null');
		}

		$sendResult = \core\SendResult::Success;

		while($message !== null){
			$this->outgoingMessagesTracer->logEvent('[o]', __FILE__, __LINE__, PHP_EOL.$message);

			$result = $this->telegramAPI->send(
				$telegram_id,
				$message->getText(),
				$message->textContainsMarkup(),
				$message->URLExpandEnabled(),
				$message->getResponseOptions()
			);

			$this->outgoingMessagesTracer->logEvent(
				'[o]', __FILE__, __LINE__,
				"Returned code: $result[code]"
			);

			if($result['code'] >= 400){
				$sendResult = \core\SendResult::Fail;
			}

			$message = $message->nextMessage();
		}

		return $sendResult;
	}
}
		
