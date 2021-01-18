<?php

namespace core;

require_once(__DIR__.'/BotPDO.php');
require_once(__DIR__.'/../lib/Tracer/TracerFactory.php');

require_once(__DIR__.'/NotificationGenerator.php');
require_once(__DIR__.'/MessageRouter.php');
require_once(__DIR__.'/MessageRouterFactory.php');

class AdminReports{
	private $tracer;

	public function __construct(){
		$pdo = \BotPDO::getInstance();
		$this->tracer = \TracerFactory::getTracer(__CLASS__, $pdo);

		$this->config = \Config::getConfig($pdo);
		$this->notificationGenerator = new NotificationGenerator();
		$this->messageRouter = MessageRouterFactory::getInstance();
	}

	private function sendErrorYardReport(): int {

		$report = $this->notificationGenerator->errorYardDailyReport();
		if($report === null){
			$this->tracer->logWarning(
				'[o]', __FILE__, __LINE__,
				'An empty report was generated.'
			);

			return false;
		}

		$route = $this->messageRouter->getRoute($report->getUser());
		$sendResult = $route->send($report->getOutgoingMessage());
		assert(count($sendResult) === 1);

		return $sendResult[0] === \core\SendResult::Success;
	}

	public function sendReports(){
		try{
			$errorYardReportEnabled = $this->config->getValue('Admin Notifications', 'Error Yard Reports Enabled', 'N');
			if($errorYardReportEnabled === 'Y'){
				$this->tracer->logDebug(
					'[o]', __FILE__, __LINE__,
					'Going to create & send an Error Yard Report.'
				);

				$res = $this->sendErrorYardReport();
				
				if($res){
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
	}
}
