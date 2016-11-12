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
					`users`.`telegram_id`,
					`shows`.`title_ru` AS showTitle,
					`series`.`title_ru` AS seriesTitle,
					`series`.`seasonNumber`,
					`series`.`seriesNumber`
			FROM `notificationsQueue`
			LEFT JOIN `users` ON `notificationsQueue`.`user_id` = `users`.`id`
			LEFT JOIN `series` ON `notificationsQueue`.`series_id` = `series`.`id`
			LEFT JOIN `shows` ON `series`.`show_id` = `shows`.`id`
			WHERE	`notificationsQueue`.`responseCode` != 200
			AND		`notificationsQueue`.`retryCount`	<  :maxRetryCount
		');
		
		$this->setNotificationDeliveryResult = $this->pdo->prepare('
			CALL notificationDeliveryResult(:notificationId, :HTTPCode);
		');
		
	}
	
	public function run(){
		$this->pdo->query('LOCK TABLES notificationsQueue WRITE');


		$this->getNotificationData->execute(
			array(
				'maxRetryCount' => MAX_NOTIFICATION_RETRY_COUNT
			)
		);

		
		while($notification = $this->getNotificationData->fetch(PDO::FETCH_ASSOC)){
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

		$this->pdo->query('UNLOCK TABLES');
	}
}
			
				
