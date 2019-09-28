<?php

namespace core;

require_once(__DIR__.'/BotPDO.php');
require_once(__DIR__.'/NotificationGenerator.php');
require_once(__DIR__.'/MessageRouter.php');
require_once(__DIR__.'/MessageRouterFactory.php');
require_once(__DIR__.'/../lib/Config.php');
require_once(__DIR__.'/../lib/Tracer/Tracer.php');

class NotificationDispatcher{
	private $notificationGenerator;
	private $messageRouter;
	private $pdo;
	private $getNotificationDataQuery;
	private $setNotificationCodeQuery;
	private $tracer;
	private $maxNotificationRetries;
	
	public function __construct(NotificationGenerator $notificationGenerator){
		assert($notificationGenerator !== null);
		$this->notificationGenerator = $notificationGenerator;

		$this->messageRouter = MessageRouterFactory::getInstance();
		
		$this->tracer = new \Tracer(__CLASS__);
		
		$this->pdo = \BotPDO::getInstance();
		$this->getNotificationDataQuery = $this->pdo->prepare("
			SELECT 	`notificationsQueue`.`id`,
					`notificationsQueue`.`responseCode`,
					`notificationsQueue`.`retryCount`,
					`notificationsQueue`.`lastDeliveryAttemptTime`,
					`users`.`id` AS user_id,
					`shows`.`title_ru` AS showTitle,
					`shows`.`alias` AS showAlias,
					`series`.`title_ru` AS seriesTitle,
					`series`.`seasonNumber`,
					`series`.`seriesNumber`
			FROM `notificationsQueue`
			JOIN `users` 	ON `notificationsQueue`.`user_id` 	= `users`.`id`
			JOIN `series` 	ON `notificationsQueue`.`series_id`	= `series`.`id`
			JOIN `shows` 	ON `series`.`show_id` 				= `shows`.`id`
			WHERE (
				`notificationsQueue`.`responseCode` IS NULL OR
				`notificationsQueue`.`responseCode` BETWEEN 400 AND 599
			)
			AND (
				`notificationsQueue`.`retryCount` < (
					SELECT IFNULL (
						(
							SELECT `value`
							FROM `config`
							WHERE `section` = 'Notification Dispatcher'
							AND `item` = 'Max Attempts Count'
						),
						1
					)
				)
			)
			FOR UPDATE
		");
		
		$this->setNotificationDeliveryResult = $this->pdo->prepare('
			UPDATE 	`notificationsQueue`
			SET 	`responseCode` 				= :HTTPCode,
					`retryCount` 				= `retryCount` + 1,
					`lastDeliveryAttemptTime`	= NOW()
			WHERE 	`id` = :notificationId;
		');

	}

	private static function eligibleToBeSent($responseCode, $retryCount, $lastDeliveryAttemptTime){
		if($responseCode === null){
			return true;
		}

		if($lastDeliveryAttemptTime === null){
			throw new \Throwable('lastDeliveryAttemptTime is null but responseCode is not');
		}

		$waitTime = null;
		switch($retryCount){ # TODO: move intervals to `config` table
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
		
		$lastAttemptTime 	= new \DateTime($lastDeliveryAttemptTime);
		$currentTime		= new \DateTime();
		
		return $lastAttemptTime->add($waitTime) < $currentTime;
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
		$this->getNotificationDataQuery->execute();
		
		while($notification = $this->getNotificationDataQuery->fetch(\PDO::FETCH_ASSOC)){
			try{
				$eligible = self::eligibleToBeSent(
					$notification['responseCode'],
					$notification['retryCount'],
					$notification['lastDeliveryAttemptTime']
				);
			}
			catch(\Throwable $ex){
				$this->tracer->logException(
					'[ERROR]', __FILE__, __LINE__,
					$ex.PHP_EOL.
					print_r($notification, true)
				);
				continue;
			}
			
			if($eligible){
				try{
					$url = self::makeURL(
						$notification['showAlias'],
						intval($notification['seasonNumber']),
						intval($notification['seriesNumber'])
					);

					$outgoingMessage = $this->notificationGenerator->newSeriesEvent(
						$notification['showTitle'], 
						intval($notification['seasonNumber']),
						intval($notification['seriesNumber']), 
						$notification['seriesTitle'],
						$url
					);

					$directredOutgoingMessage = new DirectedOutgoingMessage(
						intval($notification['user_id']),
						$outgoingMessage
					);

					$route = $this->messageRouter->route($directredOutgoingMessage->getUserId());
					$sendResult = $route->send($directredOutgoingMessage->getOutgoingMessage());

					$this->setNotificationDeliveryResult->execute(
						array(
							'notificationId'	=> $notification['id'],
							'HTTPCode' 			=> $sendResult === SendResult::Success ? 200 : 400
						)# TODO: alter HTTPCode column to internal format
					);
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
}
