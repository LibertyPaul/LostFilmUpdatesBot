<?php

namespace core;

abstract class InlineOptionType{
	const MIN_OPTION	= 2;

	const Option		= 1;
	const ExternalLink	= 2;
	const ShareButton	= 3;

	const MAX_OPTION	= 3;
}

class InlineOption{
	private $text;
	private $type;
	private $payload;

	public function __construct($text, $type, $payload){
		assert(is_string($text));
		assert(is_int($type));
		assert($payload === null || is_string($payload));

		if($type < InlineOptionType::MIN_OPTION || $type > InlineOptionType::MAX_OPTION){
			throw new \LogicException("Unknown Inline Option Type: [$type]");
		}

		$this->text = $text;
		$this->type = $type;
		$this->payload = $payload;
	}

	public function getText(){
		return $this->text;
	}

	public function getType(){
		return $this->type;
	}

	public function getPayload(){
		return $this->payload;
	}

	public function __toString(){
		switch($this->type){
			case InlineOptionType::Option:
				$typeStr = 'Type:Option';
				break;

			case InlineOptionType::ExternalLink:
				$typeStr = 'Type:URL';
				break;

			case InlineOptionType::ShareButton:
				$typeStr = 'Type:Share';
				break;
		}


		$result  = sprintf('Text: [%s]', $this->text)		.PHP_EOL;
		$result .= sprintf('Type: [%s]', $typeStr)			.PHP_EOL;
		$result .= sprintf('Payload: [%s]', $this->payload);

		return $result;
	}
}

