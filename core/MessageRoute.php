<?php

namespace core;

require_once(__DIR__.'/MessageSenderInterface.php');

class MessageRoute{
	private $messageSender;
	private $APIIdentifier;

	public function __construct(MessageSenderInterface $messageSender, $APIIdentifier){
		if($messageSender === null){
			throw \InvalidArgumentException('messageSender is null');
		}

		$this->messageSender = $messageSender;
		$this->APIIdentifier = $APIIdentifier;
	}

	public function send(OutgoingMessage $message){
		return $this->messageSender->send($this->APIIdentifier, $message);
	}
}
	
