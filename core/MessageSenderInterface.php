<?php

namespace core;

require_once(__DIR__.'/OutgoingMessage.php');
require_once(__DIR__.'/MessageDeliveryResult.php');

interface MessageSenderInterface{
	public function send(int $user_id, OutgoingMessage $message): \core\MessageDeliveryResult;
}
