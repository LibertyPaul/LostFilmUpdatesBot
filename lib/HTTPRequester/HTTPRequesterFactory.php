<?php

namespace HTTPRequester;

require_once(__DIR__.'/../Config.php');
require_once(__DIR__.'/HTTPRequester.php');
require_once(__DIR__.'/FakeHTTPRequester.php');
require_once(__DIR__.'/../Tracer/TracerFactory.php');


class HTTPRequesterFactory{
	private $performActualMessageSend;
	private $undeliveredMessageStorage;
	private $tracer;

	public function __construct(\Config $config, \PDO $pdo){
		$this->tracer = \TracerFactory::getTracer(__CLASS__, $pdo);

		$res = $config->getValue('TelegramAPI', 'Perform Actual Send');
		switch($res){
			case 'Y':
				$this->performActualMessageSend = true;
				break;

			case 'N':
				$this->performActualMessageSend = false;
				break;

			case null:
				$this->tracer->logWarning(
                    __FILE__, __LINE__,
                    'TelegramAPI->Perform Actual Send parameter is not defined.' .
                    'Using default [N]'
				);
				$this->performActualMessageSend = false;
				break;

			default:
				$this->tracer->logError(
                    __FILE__, __LINE__,
                    'TelegramAPI->Perform Actual Send parameter = [$res].' .
                    'Using default [N]'
				);
				$this->performActualMessageSend = false;
				break;
		}

		
		$this->undeliveredMessageStorage = $config->getValue(
			'FakeHTTPRequester',
			'Undelivired Messages Storage',
			'./logs/UndeliviredMessages.log'
		);
	}
	

	public function getInstance(){
		if($this->performActualMessageSend){
			return new HTTPRequester();
		}
		else{
			$undelifiredMessageStorage = __DIR__.'/../../'.$this->undeliveredMessageStorage;
			return new FakeHTTPRequester($undelifiredMessageStorage);
		}
	}

}

