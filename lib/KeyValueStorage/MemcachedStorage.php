<?php

require_once(__DIR__.'/KeyValueStorageInterface.php');
require_once(__DIR__.'/../stuff.php');

class MemcachedStorage implements \KeyValueStorageInterface{
	const DOMAIN = __CLASS__;

	private $memcached;
	private $expirationSeconds;
	private $keyPrefix;

	public function __construct($keyPrefix, $expirationSeconds){
		assert(is_string($keyPrefix));
		assert(is_int($expirationSeconds));

		$this->keyPrefix = $keyPrefix;
		$this->expirationSeconds = $expirationSeconds;

		$this->memcached = \Stuff\createMemcached();
	}

	private function createGlobalKey($localKey){
		assert(is_string($localKey));
		return sprintf('%s/%s/%s', self::DOMAIN, $this->keyPrefix, $localKey);
	}

	public function getValue($localKey){
		$globalKey = $this->createGlobalKey($localKey);
		$value = $this->memcached->get($globalKey);

		$resultCode = $this->memcached->getResultCode();
		switch($resultCode){
			case Memcached::RES_SUCCESS:
				return $value;
			case Memcached::RES_NOTFOUND:
				return null;
			default:
				throw new \RuntimeException(
					"Memcached::get error: [$resultCode], globalKey=[$globalKey]"
				);
		}
	}

	public function setValue($localKey, $value){
		$globalKey = $this->createGlobalKey($localKey);
		$this->memcached->set($globalKey, $value, $this->expirationSeconds);
	
		$resultCode = $this->memcached->getResultCode();
		if($resultCode !== Memcached::RES_SUCCESS){
			throw new \RuntimeException("Memcached::set error: [$resultCode]");
		}
	}

	public function incrementValue($localKey){
		$globalKey = $this->createGlobalKey($localKey);
		$this->memcached->add($globalKey, 0);
		$this->memcached->increment($globalKey);

		$resultCode = $this->memcached->getResultCode();
		if($resultCode !== Memcached::RES_SUCCESS){
			throw new \RuntimeException("Memcached::increment error: [$resultCode]");
		}
	}

	public function deleteValue($localKey){
		$globalKey = $this->createGlobalKey($localKey);
		$this->memcached->delete($globalKey);

		$resultCode = $this->memcached->getResultCode();
		switch($resultCode){
			case Memcached::RES_SUCCESS:
			case Memcached::RES_NOTFOUND:
				return;
			default:
				throw new \RuntimeException("Memcached::delete error: [$resultCode]");
		}
	}
}







