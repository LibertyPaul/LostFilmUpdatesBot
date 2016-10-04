<?php

require_once(__DIR__.'/TelegramBotFactoryInterface.php');
require_once(__DIR__.'/TelegramBot.php');
require_once(__DIR__.'/HTTPRequester.php');
require_once(__DIR__.'/Notifier.php');

class TelegramBotFactory implements TelegramBotFactoryInterface{
	public function createBot($telegram_id, $chat_id = null){
		return new TelegramBot($telegram_id, $chat_id, new HTTPRequester(), new Notifier($this));
	}
}

