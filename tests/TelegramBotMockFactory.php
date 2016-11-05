<?php

require_once(__DIR__.'/../TelegramBotFactoryInterface.php');
require_once(__DIR__.'/../TelegramBot.php');
require_once(__DIR__.'/HTTPRequesterMock.php');

class TelegramBotMockFactory implements TelegramBotFactoryInterface{
	
	public function __construct($outputPath){
		assert(is_file($outputPath) === false);
		$this->outputPath = $outputPath;
	}
	
	public function createBot($telegram_id){
		return new TelegramBot(
			$telegram_id, 
			new HTTPRequesterMock($this->outputPath),
			new Notifier($this)
		);
	}

}
