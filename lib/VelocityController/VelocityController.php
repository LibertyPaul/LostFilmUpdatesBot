<?php

require_once(__DIR__.'/../Tracer/Tracer.php');
require_once(__DIR__.'/../Config.php');
require_once(__DIR__.'/../../core/BotPDO.php');

require_once(__DIR__.'/VelocityCounter.php');

class VelocityController{
	private $tracer;
	private $velocityCounter;
	private $maxMessagesFromBotPerSecond;
	private $maxMessagesToUserPerSecond;

	public function __construct($keyPrefix){
		assert(is_string($keyPrefix));

		$this->tracer = new \Tracer(__CLASS__);

		$this->velocityCounter = new VelocityCounter($keyPrefix);

		$config = new \Config(BotPDO::getInstance());

		$this->maxMessagesFromBotPerSecond = $config->getValue(
			'Velocity Controller',
			'Max Messages From Bot Per Second'
		);

		if($this->maxMessagesFromBotPerSecond === null){
			$this->tracer->logWarning(
				'[CONFIG]', __FILE__, __LINE__,
				'[Velocity Controller][Max Messages From Bot Per Second] parameter is not set. '.
				'Velocity check is disabled.'
			);
		}

		$this->maxMessagesToUserPerSecond = $config->getValue(
			'Velocity Controller',
			'Max Messages To User Per Second'
		);

		if($this->maxMessagesToUserPerSecond === null){
			$this->tracer->logWarning(
				'[CONFIG]', __FILE__, __LINE__,
				'[Velocity Controller][Max Messages To User Per Second] parameter is not set. '.
				'Velocity check is disabled.'
			);
		}
	}

	public function isSendingAllowed($user_id){
		if($this->maxMessagesFromBotPerSecond !== null){
			$currentBotVelocity = $this->velocityCounter->getBotVelocity();
			if($currentBotVelocity >= $this->maxMessagesFromBotPerSecond){
				$this->tracer->logNotice(
					'[VELOCITY HIT]', __FILE__, __LINE__,
					"[Max Messages From Bot Per Second] was reached: [$currentBotVelocity]"
				);
				return false;
			}
		}

		if($this->maxMessagesToUserPerSecond !== null){
			$currentUserVelocity = $this->velocityCounter->getUserVelocity($user_id);
			if($currentUserVelocity >= $this->maxMessagesToUserPerSecond){
				$this->tracer->logNotice(
					'[VELOCITY HIT]', __FILE__, __LINE__,
					"[Max Messages To User Per Second] was reached: [$currentUserVelocity]"
				);
				return false;
			}
		}

		return true;
	}

	public function messageSentEvent($user_id){
		$this->velocityCounter->messageSentEvent($user_id);
	}
}
