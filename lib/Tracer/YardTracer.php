<?php

require_once(__DIR__.'/TracerBase.php');
require_once(__DIR__.'/TracerConfig.php');
require_once(__DIR__.'/../DAL/ErrorYard/ErrorYardAccess.php');
require_once(__DIR__.'/../DAL/ErrorDictionary/ErrorDictionaryAccess.php');

class YardTracer extends TracerBase{
    private $errorDictionary;
    private $errorYard;

    public function __construct(\PDO $pdo, TracerBase $secondTracer = null){
		parent::__construct(
			new TracerConfig(__DIR__.'/YardTracerConfig.ini'),
			$secondTracer
		);

		$this->errorDictionary = new \DAL\ErrorDictionaryAccess($pdo);
		$this->errorYard = new \DAL\ErrorYardAccess($pdo);
	}

	public function __destruct(){
		parent::__destruct();
	}

	protected function log(
		string $level,
		string $tag,
		string $file,
		int $line,
		string $message
	){
		$errorRecord = new \DAL\ErrorDictionaryRecord(
			null,
			$level,
			$file,
			$line,
			$message
		);

		$errorId = $this->errorDictionary->createOrGetErrorRecordId($errorRecord);
		$errorRecord->setId($errorId);

		$this->errorYard->logEvent($errorId);
	}

}
