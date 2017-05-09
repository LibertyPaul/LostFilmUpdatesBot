<?php

namespace core;

abstract class DestinationTypes{
	const TelegramAPI	= 0;
	const VKAPI			= 1;

	private static function validateTelegramAPIIdentifier($identifier){
		if(is_int($identifier) === false){
			return false;
		}

		if($identifier < 0){
			return false;
		}

		return true;
	}

	private static function validateVKAPIIdentifier($identifier){
		if(is_int($identifier) === false){
			return false;
		}

		if($identifier < 0){
			return false;
		}

		return true;
	}		

	public static function validate($destinationType, $identifier){
		switch($destinationType){
			case DestinationTypes::TelegramAPI:
				if(self::validateTelegramAPIIdentifier($identifier) === false){
					return false;
				}
				break;

			case DestinationTypes::VKAPI:
				if(self::validateVKAPIIdentifier($identifier) === false){
					return false;
				}
				break;

			default:
				return false;
		}

		return true;
	}
}

class MessageDestination{
	private $destinationType;
	private $identifier;

	public function __construct($destinationType, $identifier){
		if(DestinationTypes::validate($destinationType, $identifier) === false){
			throw new \InvalidArgumentException("Invalid destinationType=[$destinationType] or identifier=[$identifier]");
		}

		$this->destinationType = $destinationType;
		$this->identifier = $identifier;

	}

	public function getType(){
		return $this->destinationType;
	}

	public function getIdentifier(){
		return $this->identifier;
	}
}
