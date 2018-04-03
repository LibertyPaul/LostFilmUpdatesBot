<?php

abstract class TracerLevel{
	const Critical		= 0;
	const Error			= 1;
	const Warning		= 2;
	const Notice		= 3;
	const Event			= 4;
	const Debug			= 5;

	private static $levelMap = array(
		'CRITICAL'	=> self::Critical,
		'ERROR'		=> self::Error,
		'WARNING'	=> self::Warning,
		'NOTICE'	=> self::Notice,
		'EVENT'		=> self::Event,
		'DEBUG'		=> self::Debug
	);

	public static function logEverythingLevel(){
		return self::Debug;
	}

	public static function getLevelByName($name){
		if(array_key_exists($name, self::$levelMap)){
			return self::$levelMap[$name];
		}
		else{
			throw new \OutOfBoundsException("Invalid trace level name: '$name'");
		}
	}

	public static function getNameByLevel($level){
		$key = array_keys(self::$levelMap, $level, true);
		switch(count($key)){
			case 0:
				throw new \OutOfBoundsException("Invalid trace level: '$level'");
				break;

			case 1:
				return $key[0];
				break;

			default:
				assert(false);
				return $key[0];
				break;
		}
	}
}

