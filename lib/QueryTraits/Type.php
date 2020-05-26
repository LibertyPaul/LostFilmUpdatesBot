<?php

namespace QueryTraits;

class Type{
	const MIN = 1;

	const READ = 1;
	const WRITE = 2;

	const MAX = 2;

	private $type;

	private function __construct(int $type){
		if($type < self::MIN || $type > self::MAX){
			throw new \LogicException("Invalid Query Type value: [$type].");
		}

		$this->type = $type;
	}

	public static function Read(){
		return new Type(self::READ);
	}

	public static function Write(){
		return new Type(self::WRITE);
	}

	public function getType(){
		return $this->type;
	}
}
