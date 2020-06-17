<?php

namespace TelegramAPI;

require_once(__DIR__.'/../lib/Tracer/TracerFactory.php');
require_once(__DIR__.'/../lib/Config.php');
require_once(__DIR__.'/../core/BotPDO.php');

require_once(__DIR__.'/../lib/KeyValueStorage/KeyValueStorageInterface.php');

class VelocityController{
	private $tracer;
	private $velocityCounter;
	private $maxMessagesFromBotPerSecond;
	private $maxMessagesToUserPerSecond;

	public function __construct(
		\KeyValueStorageInterface $storage,
		\TracerBase $tracer
	){
		$this->tracer = $tracer;
		$this->storage = $storage;

		$pdo = \BotPDO::getInstance();
		$config = \Config::getConfig($pdo);

		$this->maxMessagesFromBotPerSecond = $config->getValue(
			'TelegramAPI',
			'Max Messages From Bot Per Second'
		);

		if($this->maxMessagesFromBotPerSecond === null){
			$this->tracer->logWarning(
				'[CONFIG]', __FILE__, __LINE__,
				'[TelegramAPI][Max Messages From Bot Per Second] '.
				'parameter is not set. '.
				'Velocity check is disabled.'
			);
		}

		$this->maxMessagesToUserPerSecond = $config->getValue(
			'TelegramAPI',
			'Max Messages To User Per Second'
		);

		if($this->maxMessagesToUserPerSecond === null){
			$this->tracer->logWarning(
				'[CONFIG]', __FILE__, __LINE__,
				'[TelegramAPI][Max Messages To User Per Second] '.
				'parameter is not set. '.
				'Velocity check is disabled.'
			);
		}
	}

	private function getBotVelocity(){
		return $this->storage->getValue('BotVelocity');
	}

	private function getUserVelocity($user_id){
		return $this->storage->getValue("UserVelocity.$user_id");
	}

	public function isSendingAllowed($user_id){
		if($this->maxMessagesFromBotPerSecond !== null){
			$currentBotVelocity = $this->getBotVelocity();
			if($currentBotVelocity >= $this->maxMessagesFromBotPerSecond){
				$this->tracer->logNotice(
					'[VELOCITY HIT]', __FILE__, __LINE__,
					'[Max Messages From Bot Per Second] '.
					"was reached: [$currentBotVelocity]"
				);
				return false;
			}
		}

		if($this->maxMessagesToUserPerSecond !== null){
			$currentUserVelocity = $this->getUserVelocity($user_id);
			if($currentUserVelocity >= $this->maxMessagesToUserPerSecond){
				$this->tracer->logNotice(
					'[VELOCITY HIT]', __FILE__, __LINE__,
					'[Max Messages To User Per Second] '.
					"was reached: [$currentUserVelocity]"
				);
				return false;
			}
		}

		return true;
	}

	public function messageSentEvent($user_id){
		$this->storage->incrementValue('BotVelocity');
		$this->storage->incrementValue("UserVelocity.$user_id");
	}
}
