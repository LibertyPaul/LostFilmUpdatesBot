<?php

namespace core;

require_once(__DIR__.'/BotPDO.php');
require_once(__DIR__.'/../lib/Tracer/TracerFactory.php');

require_once(__DIR__.'/NotificationGenerator.php');
require_once(__DIR__.'/UpdateHandler.php');

class AdminReports{
	private $tracer;
	private $config;
	private $notificationGenerator;
	private $updateHandler;

	public function __construct(){
		$pdo = \BotPDO::getInstance();
		$this->tracer = \TracerFactory::getTracer(__CLASS__, $pdo);

		$this->config = \Config::getConfig($pdo);
		$this->notificationGenerator = new NotificationGenerator();
		$this->updateHandler = new UpdateHandler($pdo);
	}

	private function sendErrorYardReport(): int {

		$report = $this->notificationGenerator->errorYardDailyReport();
		if($report === null){
			$this->tracer->logWarning(
				'[o]', __FILE__, __LINE__,
				'An empty report was generated.'
			);

			return -1;
		}

		return $this->updateHandler->sendMessages($report, null);
	}

	public function sendReports(){
		$this->tracer->logDebug(
			'[o]', __FILE__, __LINE__,
			'Admin Reports started.'
		);

		try{
			$errorYardReportEnabled = $this->config->getValue('Admin Notifications', 'Error Yard Reports Enabled', 'N');
			if($errorYardReportEnabled === 'Y'){
				$this->tracer->logDebug(
					'[o]', __FILE__, __LINE__,
					'Going to create & send an Error Yard Report.'
				);

				$res = $this->sendErrorYardReport();
				
				if($res === 0){
					$this->tracer->logDebug(
						'[o]', __FILE__, __LINE__,
						'Success'
					);
				}
				else{
					$this->tracer->logError(
						'[o]', __FILE__, __LINE__,
						'Failure'
					);
				}
			}
			else{
				$this->tracer->logDebug(
					'[o]', __FILE__, __LINE__,
					'Error Yard Reports are disabled.'
				);
			}
		}
		catch(\Throwable $ex){
			$this->tracer->logException('[o]', __FILE__, __LINE__, $ex);
		}


		$this->tracer->logDebug(
			'[o]', __FILE__, __LINE__,
			'Admin Reports finished.'
		);
	}
}
