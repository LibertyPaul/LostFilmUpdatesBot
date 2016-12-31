<?php

require_once(__DIR__.'/TelegramBotFactoryInterface.php');
require_once(__DIR__.'/TelegramBot.php');
require_once(__DIR__.'/HTTPRequester.php');
require_once(__DIR__.'/FakeHTTPRequester.php');
require_once(__DIR__.'/Notifier.php');

class TelegramBotFactory implements TelegramBotFactoryInterface{
	public function createBot($telegram_id){
		assert(is_int($telegram_id));


		$HTTPRequester = null;

		if(PERFORM_ACTUAL_MESSAGE_SEND){
			$HTTPRequester = new HTTPRequester();
		}
		else{
			$HTTPRequester = new FakeHTTPRequester(UNDELIVERED_MESSAGE_STORAGE);
		}

		return new TelegramBot($telegram_id, $HTTPRequester, new Notifier($this));
	}
}

