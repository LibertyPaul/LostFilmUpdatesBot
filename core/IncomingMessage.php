<?php

namespace core;

require_once(__DIR__.'/../lib/CommandSubstitutor/CoreCommand.php');
require_once(__DIR__.'/MessageAPISpecificData.php');

class IncomingMessage{
	private $coreCommand;
	private $text;
	private $APISpecificData;

	public function __construct(
		\CommandSubstitutor\CoreCommand $coreCommand = null,
		string $text = null,
		MessageAPISpecificData $APISpecificData = null
	){
		$this->coreCommand = $coreCommand;
		$this->text = $text;
		$this->APISpecificData = $APISpecificData;
	}

	public function getCoreCommand(){
		return $this->coreCommand;
	}

	public function getText(){
		return $this->text;
	}

	public function getAPISpecificData(){
		return $this->APISpecificData;
	}

	public function __toString(){
		if($this->getCoreCommand() === null){
			$coreCommandStr = '<null>';
		}
		else{
			$coreCommandStr = strval($this->getCoreCommand());
		}

		$result  = '****************************************'			.PHP_EOL;
		$result .= 'IncomingMessage:'									.PHP_EOL;
		$result .= sprintf("\tCore Command:   [%s]", $coreCommandStr)	.PHP_EOL;
		$result .= sprintf("\tText:           [%s]", $this->getText())	.PHP_EOL;
		$result .= strval($this->APISpecificData)						.PHP_EOL;
		$result .= '****************************************';
		
		return $result;
	}

}
