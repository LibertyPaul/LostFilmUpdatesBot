<?php

namespace core;

require_once(__DIR__.'/../lib/CommandSubstitutor/CoreCommand.php');

class IncomingMessage{
	private $coreCommand;
	private $text;
	private $update_id;
	private $APIErrorCode;

	public function __construct(
		\CommandSubstitutor\CoreCommand $coreCommand = null,
		string $text = null,
		int $update_id = null,
		int $APIErrorCode = null
	){
		$this->coreCommand = $coreCommand;
		$this->text = $text;
		$this->update_id = $update_id;
		$this->APIErrorCode = $APIErrorCode;
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

	public function getAPIErrorCode(){
		return $this->APIErrorCode;
	}

	public function __toString(){
		$update_id = $this->getUpdateId();
		if($update_id === null){
			$update_id = '<null>';
		}

		if($this->getCoreCommand() === null){
			$coreCommandStr = '<null>';
		}
		else{
			$coreCommandStr = strval($this->getCoreCommand());
		}

		if($this->getAPIErrorCode() === null){
			$APIErrorCodeStr = '<null>';
		}
		else{
			$APIErrorCodeStr = $this->getAPIErrorCode();
		}

		$result  = '****************************************'			.PHP_EOL;
		$result .= 'IncomingMessage:'									.PHP_EOL;
		$result .= sprintf("\tCore Command:   [%s]", $coreCommandStr)	.PHP_EOL;
		$result .= sprintf("\tText:           [%s]", $this->getText())	.PHP_EOL;
		$result .= sprintf("\tUpdate Id:      [%s]", $update_id)		.PHP_EOL;
		$result .= sprintf("\tAPI Error Code: [%s]", $APIErrorCodeStr)	.PHP_EOL;
		$result .= '****************************************';
		
		return $result;
	}

}
