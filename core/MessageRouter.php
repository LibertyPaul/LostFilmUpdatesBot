<?php

namespace core;

require_once(__DIR__.'/OutgoingMessage.php');
require_once(__DIR__.'/MessageRoute.php');
require_once(__DIR__.'/../lib/Tracer/Tracer.php');
require_once(__DIR__.'/../TelegramAPI/MessageSender.php');

class MessageRouter{
	private $messageSenders; # array('APIName' => MessageSender)
	private $tracer;

	public function __construct(array $messageSenders){
		$this->tracer = new \Tracer(__CLASS__);

		$this->messageSenders = array();

		foreach($messageSenders as $APIName => $messageSender){
			if($messageSender instanceof MessageSenderInterface === false){
				$this->tracer->logError(
					'[ROUTER]', __FILE__, __LINE__,
					"Sender to '$APIName' is not of valid type: ".
					PHP_EOL.print_r($messageSender, true)
				);
				continue;
			}

			$this->messageSenders[$APIName] = $messageSender;
		}
	}

	private function getMessageSender(string $API){
		if(array_key_exists($API, $this->messageSenders) === false){
			$this->tracer->logError(
				'[DATA]', __FILE__, __LINE__,
				"Unknown API '$API'".PHP_EOL.
				'Available APIs:'.PHP_EOL.
				print_r($this->messageSenders, true)
			);
			
			throw new \RuntimeException("Unknown API '$API'");
		}

		return $this->messageSenders[$API];
	}
		

	public function route(\DAL\User $user){
		$messageSender = $this->getMessageSender($user->getAPI());
		$route = new MessageRoute($messageSender, $user->getId());
		return $route;
	}
}
		



