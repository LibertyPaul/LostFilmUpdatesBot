<?php

namespace core;

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

	public static function NO_MARKUP(): self {
		return new MarkupType(MarkupTypeEnum::NoMarkup);
	}

	public static function HTML(): self {
		return new MarkupType(MarkupTypeEnum::HTML);
	}

	public static function TELEGRAM(): self {
		return new MarkupType(MarkupTypeEnum::Telegram);
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
				throw new \LogicException("Invalid type [$this->type]");
		}
	}
}
		

