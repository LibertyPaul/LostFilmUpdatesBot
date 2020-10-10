<?php

namespace core;

require_once(__DIR__.'/MessageSenderInterface.php');

class MessageRoute{
	private $user_id;
	private $messageSender;

	public function __construct(MessageSenderInterface $messageSender, $user_id){
		$this->messageSender = $messageSender;
		$this->user_id = $user_id;
	}

	public function send(OutgoingMessage $message): array{
		return $this->messageSender->send($this->user_id, $message);
	}
}
	
