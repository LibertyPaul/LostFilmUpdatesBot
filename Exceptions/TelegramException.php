<?php
require_once(__DIR__."/UserException.php");
require_once(__DIR__."/../TelegramBot_base.php");


class TelegramException extends UserException{
	protected $telegram_id;

	public function __construct($telegram_id, $errorText){
		parent::__construct($errorText);
		$this->telegram_id = $telegram_id;
	}
	
	public function showErrorText(){
		$bot = new TelegramBot(intval($this->telegram_id));
		$bot->sendMessage(
			array(
				'text' => $this->getMessage(),
				'reply_markup' => array(
					'hide_keyboard' => true
				)
			)
		);
		
		
	}
}
