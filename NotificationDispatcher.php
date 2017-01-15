<?php
require_once(__DIR__.'/config/stuff.php');
require_once(__DIR__.'/Notifier.php');

class NotificationDispatcher{
	private $notifier;
	private $pdo;
	private $getNotificationData;
	private $setNotificationCode;
	private $bots;
	
	public function __construct(Notifier $notifier){
		assert($notifier !== null);
		$this->notifier = $notifier;
		
		$this->pdo = createPDO();
		$this->getNotificationData = $this->pdo->prepare('
			SELECT 	`notificationsQueue`.`id`,
					`notificationsQueue`.`responseCode`,
					`notificationsQueue`.`retryCount`,
					`notificationsQueue`.`lastDeliveryAttemptTime`,
					`users`.`telegram_id`,
					`shows`.`title_ru` AS showTitle,
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
			) AND
				`notificationsQueue`.`retryCount` < :maxRetryCount
		');
		
		$this->setNotificationDeliveryResult = $this->pdo->prepare('
			CALL notificationDeliveryResult(:notificationId, :HTTPCode);
		');
		
	}

	private function shallBeSent($responseCode, $retryCount, $lastDeliveryAttemptTime){
		if($responseCode === null){
			return true;
		}

		if($responseCode >= 400 && $responseCode <= 599 && $retryCount < MAX_NOTIFICATION_RETRY_COUNT){

			$waitTime	= null;
			switch($retryCount){
				case 0:
					$waitTime = new DateInterval('PT0S');
					break;
				
				case 1:
					$waitTime = new DateInterval('PT1M');
					break;

				case 2:
					$waitTime = new DateInterval('PT15M');
					break;

				case 3:
					$waitTime = new DateInterval('PT1H');
					break;

				case 4:
					$waitTime = new DateInterval('P1D');
					break;

				default:
					throw Exception("Incorrect retryCount ($retryCount)");
			}
			
			$lastAttemptTime 	= new DateTime($lastDeliveryAttemptTime);
			$currentTime		= new DateTime();
			
			return $lastAttemptTime->add($waitTime) >= $currentTime;
		}
		else{
			return false;
		}
	}
	
	public function run(){
		$this->pdo->query('
			LOCK TABLES 
				notificationsQueue	WRITE,
				users				READ,
				series				READ,
				shows				READ;
		');

		$this->getNotificationData->execute(
			array(
				'maxRetryCount' => MAX_NOTIFICATION_RETRY_COUNT
			)
		);

		
		while($notification = $this->getNotificationData->fetch(PDO::FETCH_ASSOC)){
			if(
				$this->shallBeSent(
					intval($notification['responseCode']),
					intval($notification['retryCount']),
					intval($notification['lastDeliveryAttemptTime'])
				)
			){

				$result = $this->notifier->newSeriesEvent(
					intval($notification['telegram_id']), 
					$notification['showTitle'], 
					intval($notification['seasonNumber']),
					intval($notification['seriesNumber']), 
					$notification['seriesTitle']
				);
				
				$this->setNotificationDeliveryResult->execute(
					array(
						'notificationId'	=> $notification['id'],
						'HTTPCode' 			=> $result['code']
					)
				);
			}
		}

		$this->pdo->query('UNLOCK TABLES');
	}
}
			
				
