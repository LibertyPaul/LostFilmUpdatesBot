<?php

require_once(__DIR__.'/../TelegramBotFactoryInterface.php');
require_once(__DIR__.'/../TelegramBot.php');
require_once(__DIR__.'/HTTPRequesterMock.php');

class TelegramBotMockFactory implements TelegramBotFactoryInterface{
	private $outputPath;
	
	public function __construct($outputPath){
		$this->setOutputPath($outputPath);
	}
	
	public function setOutputPath($outputPath){
		if(is_file($outputPath) === false){
			throw new Exception("Invalid path $outputPath");
		}
		
		$this->outputPath = $outputPath;
	}
	
	public function createBot($telegram_id, $chat_id = null){
		return new TelegramBot($telegram_id, $chat_id, new HTTPRequesterMock($this->outputPath), new Notifier($this));
	}
}
