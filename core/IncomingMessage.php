<?php

namespace core;

require_once(__DIR__.'/../lib/CommandSubstitutor/CoreCommand.php');


class IncomingMessage{
	private $user_id;
	private $coreCommand;
	private $text;
	private $update_id;

	public function __construct(
		$user_id,
		\CommandSubstitutor\CoreCommand $coreCommand = null,
		$text,
		$rawMessage = null,
		$update_id = null
	){
		assert(is_int($user_id));
		assert(is_string($text));

		$this->user_id = $user_id;
		$this->coreCommand = $coreCommand;
		$this->text = $text;
		$this->update_id = $update_id;
	}

	public function getUserId(){
		return $this->user_id;
	}

	public function getCoreCommand(){
		return $this->coreCommand;
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

		$coreCommandStr = null;
		if($this->coreCommand !== null){
			$coreCommandStr = $this->coreCommand->getText();
		}

		$result  = '***********************************'				.PHP_EOL;
		$result .= 'IncomingMessage:'									.PHP_EOL;
		$result .= sprintf("\tUser Id:      [%d]", $this->getUserId())	.PHP_EOL;
		$result .= sprintf("\tCore Command: [%s]", $coreCommandStr)		.PHP_EOL;
		$result .= sprintf("\tText:         [%s]", $this->getText())	.PHP_EOL;
		$result .= sprintf("\tUpdate Id:    [%s]", $update_id)			.PHP_EOL;
		$result .= '***********************************';
		
		return $result;
	}

}
