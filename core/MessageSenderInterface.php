<?php

namespace core;

require_once(__DIR__.'/OutgoingMessage.php');

abstract class SendResult{
	const Success	= 0;
	const Fail		= 1;
}

interface MessageSenderInterface{
	public function send(int $user_id, OutgoingMessage $message);
}
