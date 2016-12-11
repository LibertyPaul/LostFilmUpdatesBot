<?php

require_once(__DIR__.'/../TelegramBotFactoryInterface.php');
require_once(__DIR__.'/../TelegramBot.php');
require_once(__DIR__.'/../FakeHTTPRequester.php');

class TelegramBotMockFactory implements TelegramBotFactoryInterface{
	
	public function __construct($outputPath){
		assert(is_file($outputPath));
		$this->outputPath = $outputPath;
	}
	
	public function createBot($telegram_id){
		return new TelegramBot(
			$telegram_id, 
			new FakeHTTPRequester($this->outputPath),
			new Notifier($this)
		);
	}

}
