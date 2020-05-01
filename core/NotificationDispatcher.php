<?php

namespace core;

require_once(__DIR__.'/BotPDO.php');
require_once(__DIR__.'/NotificationGenerator.php');
require_once(__DIR__.'/MessageRouter.php');
require_once(__DIR__.'/MessageRouterFactory.php');
require_once(__DIR__.'/../lib/Config.php');
require_once(__DIR__.'/../lib/Tracer/Tracer.php');

require_once(__DIR__.'/../lib/DAL/Users/UsersAccess.php');
require_once(__DIR__.'/../lib/DAL/Series/SeriesAccess.php');
require_once(__DIR__.'/../lib/DAL/Shows/ShowsAccess.php');
require_once(__DIR__.'/../lib/DAL/NotificationsQueue/NotificationsQueueAccess.php');

class NotificationDispatcher{
	private $notificationGenerator;
	private $messageRouter;
	private $tracer;
	
	public function __construct(NotificationGenerator $notificationGenerator){
		$this->notificationGenerator = $notificationGenerator;

		$this->messageRouter = MessageRouterFactory::getInstance();
		
		$this->tracer = new \Tracer(__CLASS__);
		
		$this->pdo = \BotPDO::getInstance();

		$this->config = new \Config($this->pdo, \ConfigFetchMode::PER_REQUEST);
		$this->maxRetryCount = $this->config->getValue(
			'Notification Dispatcher',
			'Max Attempts Count',
			5
		);

		$this->usersAccess = new \DAL\UsersAccess($this->tracer, $this->pdo);
		$this->seriesAccess = new \DAL\SeriesAccess($this->tracer, $this->pdo);
		$this->showsAccess = new \DAL\ShowsAccess($this->tracer, $this->pdo);
		$this->notificationsQueueAccess = new \DAL\NotificationsQueueAccess($this->tracer, $this->pdo);
	}

	private static function eligibleToBeSent(\DAL\Notification $notification){
		if($notification->getResponseCode() === null){
			return true;
		}

		if($notification->getLastDeliveryAttemptTime() === null){
			throw new \LogicException('lastDeliveryAttemptTime is null but responseCode is not');
		}

		$waitTime = null;
		switch($notification->getRetryCount()){ # TODO: move intervals to `config` table
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
		$nextDeliveryTime = $notification-->getLastDeliveryAttemptTime()->add($waitTime);
		
		return $nextDeliveryTime < $currentTime;
	}
	
	private static function makeURL($showAlias, $seasonNumber, $seriesNumber){
		return sprintf(
			'https://www.lostfilm.tv/series/%s/season_%d/episode_%d',
			$showAlias,
			$seasonNumber,
			$seriesNumber
		);
	}

	public function run(){
		$notifications = $this->notificationsQueueAccess->getPendingNotifications($this->maxRetryCount);

		foreach($notifications as $notification){
			try{
				$eligible = self::eligibleToBeSent($notification);
			}
			catch(\Throwable $ex){
				$this->tracer->logException('[ERROR]', __FILE__, __LINE__, $ex);
				$this->tracer->logfDebug('[ERROR]', __FILE__, __LINE__, "\n%s\n", $notification);
				continue;
			}
			
			if($eligible === false){
				continue;
			}

			try{
				$user = $this->usersAccess->getUserById($notification->getUserId());
				$series = $this->seriesAccess->getSeriesById($notification->getSeriesId());
				$show = $this->showsAccess->getShowById($series->getShowId());

				$url = self::makeURL(
					$show->getAlias(),
					$series->getSeasonNumber(),
					$series->getSeriesNumber()
				);

				$outgoingMessage = $this->notificationGenerator->newSeriesEvent(
					$show->getTitleRu(),
					$series->getSeasonNumber(),
					$series->getSeriesNumber(),
					$series->getTitleRu(),
					$url
				);

				$directredOutgoingMessage = new DirectedOutgoingMessage($user, $outgoingMessage);

				$route = $this->messageRouter->route($directredOutgoingMessage->getUser());
				$sendResult = $route->send($directredOutgoingMessage->getOutgoingMessage());

				$notification->applyDeliveryResult($sendResult === SendResult::Success ? 200 : 400);
				$this->notificationsQueueAccess->updateNotification($notification);
			}
			catch(\PDOException $ex){
				$this->tracer->logException('[DB ERROR]', __FILE__, __LINE__, $ex);
				continue;
			}
			catch(\Throwable $ex){
				$this->tracer->logException('[ERROR]', __FILE__, __LINE__, $ex);
				continue;
			}
		}
	}
}
