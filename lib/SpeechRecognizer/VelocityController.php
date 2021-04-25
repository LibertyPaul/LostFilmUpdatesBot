<?php

namespace SpeechRecognizer;

require_once(__DIR__.'/../Tracer/TracerFactory.php');
require_once(__DIR__.'/../KeyValueStorage/DBStorage.php');
require_once(__DIR__.'/../Config.php');

class VelocityController{
	private $tracer;
	private $storage;
	private $maxRecognitionsPerUserPerWeek;
	private $maxRecognitionsPerBotPerMonth;
    private $maxSearchesPerUserPerWeek;

    public function __construct(
		\Config $config,
		\DBStorage $storage,
		\PDO $pdo
	){
		$this->tracer = \TracerFactory::getTracer(__CLASS__, $pdo);
		$this->storage = $storage;

		$this->maxSearchesPerUserPerWeek = $config->getValue(
			'Speech Recognizer',
			'Max Recognitions Per User Per Week'
		);

		if($this->maxSearchesPerUserPerWeek === null){
			$this->tracer->logWarning(
				'[CONFIG]', __FILE__, __LINE__,
				'[Speech Recognizer][Max Recognitions Per User Per Week] value is not set. '.
				'Velocity check will be skipped.'
			);
		}

		$this->maxRecognitionsPerBotPerMonth = $config->getValue(
			'Speech Recognizer',
			'Max Recognitions Per Bot Per Month'
		);

		if($this->maxRecognitionsPerBotPerMonth === null){
			$this->tracer->logWarning(
				'[CONFIG]', __FILE__, __LINE__,
				'[Speech Recognizer][Max Recognitions Per Bot Per Month] value is not set. '.
				'Velocity check will be skipped.'
			);
		}
	}

	private function checkUserLimits($user_id){
		$userVelocity = $this->storage->getValue("User#$user_id");
		if($userVelocity < 


	public function isRecognitionAllowed($user_id){
		
