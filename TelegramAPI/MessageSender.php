<?php

namespace TelegramAPI;

require_once(__DIR__.'/../core/MessageSenderInterface.php');
require_once(__DIR__.'/../core/OutgoingMessage.php');
require_once(__DIR__.'/../lib/Tracer/Tracer.php');
require_once(__DIR__.'/TelegramAPI.php');
require_once(__DIR__.'/../core/BotPDO.php');

class MessageSender implements \core\MessageSenderInterface{
	private $tracer;
	private $outgoingMessagesTracer;
	private $telegramAPI;
	private $getTelegramIdQuery;

	public function __construct(TelegramAPI $telegramAPI){
		assert($telegramAPI !== null);
		$this->telegramAPI = $telegramAPI;

		$this->tracer = new \Tracer(__CLASS__);
		$this->outgoingMessagesTracer = new \Tracer('OutgoingMessages');

		$pdo = \BotPDO::getInstance();

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
		if($message === null){
			$this->tracer->logError('[o]', __FILE__, __LINE__, 'OutgoingMessage is null');
			throw new \InvalidArgumentException('OutgoingMessage is null');
		}

		$telegram_id = $this->getTelegramId($user_id);

		$sendResult = \core\SendResult::Success;

		while($message !== null){
			$this->outgoingMessagesTracer->logEvent('[o]', __FILE__, __LINE__, PHP_EOL.$message);

			$result = $this->telegramAPI->send(
				$telegram_id,
				$message->getText(),
				$message->textContainsMarkup(),
				$message->URLExpandEnabled(),
				$message->getResponseOptions(),
				$message->getShareBotContact()
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
		
