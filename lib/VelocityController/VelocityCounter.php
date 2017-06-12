<?php
require_once(__DIR__.'/../stuff.php');

class VelocityCounter{
	const SECTION = __CLASS__;
	const EXPIRATION_TIME = 5;

	private $memcache;
	private $keyPrefix;

	public function __construct($keyPrefix){
		assert(is_string($keyPrefix));
		$this->keyPrefix = $keyPrefix;

		$this->memcache = Stuff\createMemcache();
	}

	private function getUserVelocityKey($user_id){
		assert(is_int($user_id));
		return self::SECTION.'/'.$this->keyPrefix.'/'.$user_id.'/'.time();
	}

	private function getBotVelocityKey(){
		return self::SECTION.'/'.$this->keyPrefix.'/BotVelocity/'.time();
	}

	private function getCounterValue($key){
		$value = $this->memcache->get($key);
		if($value === false){
			$value = 0;
		}

		return $value;
	}

	private function incrementCounter($key){ // concurrent-safe memcache-based counter
		$this->memcache->add($key, 0, 0, self::EXPIRATION_TIME); // will be ignored if exists
		$this->memcache->increment($key);
	}

	private function incrementUserCounter($user_id){
		$key = $this->getUserVelocityKey($user_id);
		$this->incrementCounter($key);
	}
	
	private function incrementBotCounter(){
		$key = $this->getBotVelocityKey();
		$this->incrementCounter($key);
	}

	# Public methods:

	public function messageSentEvent($user_id){
		$this->incrementUserCounter($user_id);
		$this->incrementBotCounter();
	}

	public function getUserVelocity($user_id){
		$key = $this->getUserVelocityKey($user_id);
		return $this->getCounterValue($key);
	}

	public function getBotVelocity(){
		$key = $this->getBotVelocityKey();
		return $this->getCounterValue($key);
	}
}
