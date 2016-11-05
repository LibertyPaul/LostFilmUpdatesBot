<?php
require_once(__DIR__."/config/stuff.php");
require_once(__DIR__.'/Notifier.php');

class NotificationDispatcher{
	private $notifier;
	private $getNotificationData;
	private $setNotificationCode;
	private $bots;
	
	public function __construct(Notifier $notifier){
		assert($notifier !== null);
		$this->notifier = $notifier;
		
		$pdo = createPDO();
		$this->getNotificationData = $pdo->prepare('
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
			WHERE `notificationsQueue`.`responceCode` IS NULL
		');
		
		$this->setNotificationCode = $pdo->prepare('
			UPDATE `notificationsQueue`
			SET `responceCode` = :responceCode
		');
		
	}
	
	public function run(){
		$this->getNotificationData->execute();
		
		while($notification = $this->getNotificationData->fetch(PDO::FETCH_ASSOC)){
			$result = $this->notifier->newSeriesEvent(
				intval($notification['telegram_id']), 
				$notification['showTitle'], 
				intval($notification['seasonNumber']),
				intval($notification['seriesNumber']), 
				$notification['seriesTitle']
			);
			
			$this->setNotificationCode->execute(
				array(
					':responceCode' => $result['code']
				)
			);
			
		}
	}
}
			
				
