<?php

namespace core;

require_once(__DIR__.'/BotPDO.php');
require_once(__DIR__.'/NotificationGenerator.php');
require_once(__DIR__.'/MessageRouter.php');
require_once(__DIR__.'/MessageRouterFactory.php');
require_once(__DIR__.'/../lib/Config.php');
require_once(__DIR__.'/../lib/Tracer/TracerFactory.php');

require_once(__DIR__.'/../lib/DAL/Users/UsersAccess.php');
require_once(__DIR__.'/../lib/DAL/Series/SeriesAccess.php');
require_once(__DIR__.'/../lib/DAL/Shows/ShowsAccess.php');
require_once(__DIR__.'/../lib/DAL/NotificationsQueue/NotificationsQueueAccess.php');

class NotificationDispatcher{
	private $notificationGenerator;
	private $messageRouter;
	private $tracer;
    private $pdo;
    private $config;
    private $maxRetryCount;
    private $usersAccess;
    private $seriesAccess;
    private $showsAccess;
    private $notificationsQueueAccess;

    public function __construct(NotificationGenerator $notificationGenerator){
		$this->notificationGenerator = $notificationGenerator;

		$this->messageRouter = MessageRouterFactory::getInstance();
		
		$this->pdo = \BotPDO::getInstance();
		
		$this->tracer = \TracerFactory::getTracer(__CLASS__, $this->pdo);

		$this->config = \Config::getConfig($this->pdo, \ConfigFetchMode::PER_REQUEST);
		$this->maxRetryCount = intval(
		    $this->config->getValue(
			    'Notification Dispatcher',
                'Max Attempts Count',
                5
		    )
        );

		$this->usersAccess = new \DAL\UsersAccess($this->pdo);
		$this->seriesAccess = new \DAL\SeriesAccess($this->pdo);
		$this->showsAccess = new \DAL\ShowsAccess($this->pdo);
		$this->notificationsQueueAccess = new \DAL\NotificationsQueueAccess($this->pdo);
	}

	private static function eligibleToBeSent(\DAL\Notification $notification): bool {
		if($notification->getResponseCode() === null){
			return true;
		}

		if($notification->getLastDeliveryAttemptTime() === null){
			throw new \LogicException('lastDeliveryAttemptTime is null but responseCode is not');
		}


        $retryCount = $notification->getRetryCount();
        switch($retryCount){
			case 0:
				$interval = 'PT0S';
				break;
			
			case 1:
				$interval = 'PT1M';
				break;

			case 2:
				$interval = 'PT15M';
				break;

			case 3:
				$interval = 'PT1H';
				break;

			case 4:
				$interval = 'P1D';
				break;

			default:
				$daysExtra = $retryCount - 3;
				$interval = sprintf('P%dD', $daysExtra);
				break;
		}

		$waitTime = new \DateInterval($interval);
		$currentTime = new \DateTimeImmutable();
		$nextDeliveryTime = $notification->getLastDeliveryAttemptTime()->add($waitTime);
		
		return $nextDeliveryTime < $currentTime;
	}

	public function run(){
		$notifications = $this->notificationsQueueAccess->getPendingNotifications($this->maxRetryCount);

		foreach($notifications as $notification){
			try{
				$eligible = self::eligibleToBeSent($notification);
			}
			catch(\Throwable $ex){
				$this->tracer->logException(__FILE__, __LINE__, $ex);
				$this->tracer->logfDebug(__FILE__, __LINE__, "\n%s\n", $notification);
				continue;
			}
			
			if($eligible === false){
				continue;
			}

			try{
				$user = $this->usersAccess->getUserById($notification->getUserId());
				$series = $this->seriesAccess->getSeriesById($notification->getSeriesId());
				$show = $this->showsAccess->getShowById($series->getShowId());

				$outgoingMessage = $this->notificationGenerator->newSeriesEvent($show, $series);
				
				if($user->isDeleted() === false){
					$sendResult = $this->messageRouter->getRoute($user)->send($outgoingMessage);
				}
				else{
					$sendResult = SendResult::Fail;
				}

				$notification->applyDeliveryResult($sendResult === SendResult::Success ? 200 : 400); #TODO change code to internal status
				$this->notificationsQueueAccess->updateNotification($notification);
			}
			catch(\PDOException $ex){
				$this->tracer->logException(__FILE__, __LINE__, $ex);
				continue;
			}
			catch(\Throwable $ex){
				$this->tracer->logException(__FILE__, __LINE__, $ex);
				continue;
			}
		}
	}
}
