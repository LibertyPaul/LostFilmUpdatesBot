<?php

namespace core;

require_once(__DIR__.'/OutgoingMessage.php');
require_once(__DIR__.'/MessageRoute.php');
require_once(__DIR__.'/../TelegramAPI/MessageSender.php');

class MessageRouter{
	private $messageSenders; # array('APIName' => MessageSender)
	private $tracer;

	public function __construct(array $messageSenders){

		$this->messageSenders = array();

		foreach($messageSenders as $APIName => $messageSender){
			if($messageSender instanceof MessageSenderInterface === false){
				throw new \LogicException("Sender to '$APIName' is not of valid type: ".gettype($messageSender));
			}

			$this->messageSenders[$APIName] = $messageSender;
		}
	}

	private function getMessageSender(string $API){
		if(array_key_exists($API, $this->messageSenders) === false){
			throw new \LogicException("Unknown API '$API'.");
		}

		return $this->messageSenders[$API];
	}
		

	public function route(\DAL\User $user){
		$messageSender = $this->getMessageSender($user->getAPI());
		$route = new MessageRoute($messageSender, $user->getId());
		return $route;
	}
}
		



