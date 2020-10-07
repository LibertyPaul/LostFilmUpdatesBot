<?php

namespace QueryTraits;

class ApproachMismatchException extends \RuntimeException{
	public function __construct(Approach $approach, int $actual){
		$errorMessage = sprintf(
			"Approach [%s] -- Actual [%d]",
			$approach,
			$actual
		);

		parent::__construct($errorMessage);
	}
}

class Approach{
	const MIN			= 1;

	const ONE_IF_EXISTS	= 1;
	const ONE			= 2;
	const MANY			= 3;

	const MAX			= 3;

	private $approach;

	private function __construct(int $approach){
		if($approach < self::MIN || $approach > self::MAX){
			throw new \RuntimeException("Invalid approach type: ".$approach);
		}

		$this->approach = $approach;
	}

	public static function OneIfExists(){
		return new Approach(self::ONE_IF_EXISTS);
	}

	public static function One(){
		return new Approach(self::ONE);
	}

	public static function Many(){
		return new Approach(self::MANY);
	}

	public function getApproach(): int{
		return $this->approach;
	}

	public function verify(int $rowsAffected){
		switch($this->approach){
		case self::ONE:
			if($rowsAffected !== 1){
				throw new ApproachMismatchException($this, $rowsAffected);
			}
			break;

		case self::ONE_IF_EXISTS:
			if($rowsAffected !== 0 && $rowsAffected !== 1){
				throw new ApproachMismatchException($this, $rowsAffected);
			}
			break;

		case self::MANY:
			return true;
			break;
		}
	}

	public function repack(array $objects){
		switch($this->approach){
		case self::ONE:
			return $objects[0];

		case self::ONE_IF_EXISTS:
			if(empty($objects)){
				return null;
			}
			else{
				return $objects[0];
			}

		case self::MANY:
			return $objects;
		}
	}
			

	public function __toString(){
		switch($this->approach){
		case self::ONE:
			return "ONE";

		case self::ONE_IF_EXISTS:
			return "ONE_IF_EXISTS";

		case self::MANY:
			return "MANY";

		default:
			return "UNKNOWN";
		}
	}
}

