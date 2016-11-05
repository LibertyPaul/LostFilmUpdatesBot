<?php
require_once(__DIR__.'/UserException.php');
require_once(__DIR__.'/../TelegramBot.php');
require_once(__DIR__.'/../HTTPRequester.php');


class TelegramException extends UserException{
	private $bot;

	public function __construct(TelegramBot $bot, $errorText){
		parent::__construct($errorText);

		assert($bot !== null);
		$this->bot = $bot;
	}
	
	public function release(){
		$this->bot->sendMessage(
			array(
				'text' => $this->getMessage(),
				'reply_markup' => array(
					'hide_keyboard' => true
				)
			)
		);
	}

}
