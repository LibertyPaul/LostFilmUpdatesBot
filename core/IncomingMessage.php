<?php

namespace core;

require_once(__DIR__.'/UserCommand.php');


class IncomingMessage{
	private $user_id;
	private $userCommand;
	private $text;
	private $update_id;

	public function __construct(
		$user_id,
		UserCommand $userCommand = null,
		$text,
		$rawMessage = null,
		$update_id = null
	){
		assert(is_int($user_id));
		assert(is_string($text));

		$this->user_id = $user_id;
		$this->userCommand = $userCommand;
		$this->text = $text;
		$this->update_id = $update_id;
	}

	public function getUserId(){
		return $this->user_id;
	}

	public function getUserCommand(){
		return $this->userCommand;
	}

	public function getText(){
		return $this->text;
	}

	public function getUpdateId(){
		return $this->update_id;
	}

	public function __toString(){
		$update_id = $this->getUpdateId();
		if($update_id === null){
			$update_id = '';
		}

		$result  = '***********************************'					.PHP_EOL;
		$result .= 'IncomingMessage:'										.PHP_EOL;
		$result .= sprintf("\tUser Id:      [%d]", $this->getUserId())		.PHP_EOL;
		$result .= sprintf("\tUser Command: [%s]", $this->getUserCommand())	.PHP_EOL;
		$result .= sprintf("\tText:         [%s]", $this->getText())		.PHP_EOL;
		$result .= sprintf("\tUpdate Id:    [%s]", $update_id)				.PHP_EOL;
		$result .= '***********************************';
		
		return $result;
	}

}
