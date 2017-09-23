<?php

abstract class MarkupTypeEnum{
	const NoMarkup	= 0;
	const HTML		= 1;
	const Telegram	= 2;
}

class MarkupType{
	private $type;

	public function __construct($type){
		switch($type){
			case MarkupTypeEnum::NoMarkup:
			case MarkupTypeEnum::HTML:
			case MarkupTypeEnum::Telegram:
				$this->type = $type;
				break;

			default:
				throw new \InvalidArgumentException("Invalid value: [$type]");
		}
	}

	public function get(){
		return $this->type;
	}

	public function __toString(){
		switch($this->type){
			case MarkupTypeEnum::NoMarkup:	return 'NoMarkup';
			case MarkupTypeEnum::HTML:		return 'HTML';
			case MarkupTypeEnum::Telegram:	return 'Telegram';
			default:
				assert(false);
				return 'Unknown';
		}
	}
}
		

