<?php

require_once(__DIR__.'/KeyValueStorageInterface.php');
require_once(__DIR__.'/../../core/BotPDO.php');

class DBStorage implements \KeyValueStorageInterface{
	const DOMAIN = __CLASS__;

	private $pdo;
	private $expirationSeconds;
	private $keyPrefix;

	private $getValueQuery;
	private $insertOrUpdateValueQuery;
	private $incrementValueQuery;
	private $deleteValueQuery;


	public function __construct($keyPrefix, $valueLifeTimeSeconds){
		assert(is_string($keyPrefix));
		assert(is_int($valueLifeTimeSeconds));

		$this->keyPrefix = $keyPrefix;
		$this->valueLifeTimeSeconds = $valueLifeTimeSeconds;

		$pdo = \BotPDO::getInstance(); #TODO: create separate DB user

		$this->getValueQuery = $pdo->prepare('
			SELECT `value`
			FROM `KeyValueStorage`
			WHERE `key` = :key
		');

		$this->insertOrUpdateValueQuery = $pdo->prepare('
			INSERT INTO `KeyValueStorage` (`key`, `value`, `keepUntil`)
			VALUES (:key, :value, FROM_UNIXTIME(UNIX_TIMESTAMP(NOW()) + :keepDuration))
			ON DUPLICATE KEY UPDATE
				`value`		= :value,
				`keepUntil`	= FROM_UNIXTIME(UNIX_TIMESTAMP(NOW()) + :keepDuration)
		');

		$this->incrementValueQuery = $pdo->prepare('
			INSERT INTO `KeyValueStorage` (`key`, `value`, `keepUntil`)
			VALUES (:key, 1, FROM_UNIXTIME(UNIX_TIMESTAMP(NOW()) + :keepDuration))
			ON DUPLICATE KEY UPDATE
				`value`		= `value` + 1,
				`keepUntil`	= FROM_UNIXTIME(UNIX_TIMESTAMP(NOW()) + :keepDuration)
		');

		$this->deleteValueQuery = $pdo->prepare('
			DELETE FROM `KeyValueStorage`
			WHERE `key` = :key
		');
	}

	private function createGlobalKey($localKey){
		return sprintf('%s/%s/%s', self::DOMAIN, $this->keyPrefix, $localKey);
	}

	public function getValue($localKey){
		$globalKey = $this->createGlobalKey($localKey);

		$this->getValueQuery->execute(
			array(
				':key' => $globalKey
			)
		);

		#TODO: catch PDOException and re-throw as internal ones

		$result = $this->getValueQuery->fetch();
		if($result === false){
			return null;
		}

		$value = $result[0];

		return $value;
	}

	public function setValue($localKey, $value){
		$globalKey = $this->createGlobalKey($localKey);

		$this->insertOrUpdateValueQuery->execute(
			array(
				':key'			=> $globalKey,
				':value'		=> $value,
				':keepDuration'	=> $this->valueLifeTimeSeconds
			)
		);

		#TODO: catch PDOException and re-throw as internal ones
	}

	public function incrementValue($localKey){
		$globalKey = $this->createGlobalKey($localKey);

		$this->incrementValueQuery->execute(
			array(
				':key'			=> $globalKey,
				':keepDuration'	=> $this->valueLifeTimeSeconds
			)
		);

		#TODO: catch PDOException and re-throw as internal ones
	}

	public function deleteValue($localKey){
		$globalKey = $this->createGlobalKey($localKey);

		$this->deleteValueQuery->execute(
			array(
				':key' => $globalKey
			)
		);

		#TODO: catch PDOException and re-throw as internal ones
	}
}
