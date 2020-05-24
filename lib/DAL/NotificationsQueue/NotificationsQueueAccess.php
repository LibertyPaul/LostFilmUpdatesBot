<?php

namespace DAL;

require_once(__DIR__.'/../CommonAccess.php');
require_once(__DIR__.'/NotificationBuilder.php');

class NotificationsQueueAccess extends CommonAccess{
	private $getPendingNotificationsQuery;
	private $updateNotificationQuery;

	public function __construct(\Tracer $tracer, \PDO $pdo){
		parent::__construct(
			$tracer,
			$pdo,
			new NotificationBuilder()
		);

		$selectFields = "
			SELECT
				`notificationsQueue`.`id`,
				`notificationsQueue`.`series_id`,
				`notificationsQueue`.`user_id`,
				`notificationsQueue`.`responseCode`,
				`notificationsQueue`.`retryCount`,
				DATE_FORMAT(`notificationsQueue`.`lastDeliveryAttemptTime`, '".parent::dateTimeDBFormat."') AS lastDeliveryAttemptTimeStr
		";

		$this->getPendingNotificationsQuery = $this->pdo->prepare(
			$selectFields."
			FROM `notificationsQueue`
			WHERE (
				`notificationsQueue`.`responseCode` IS NULL OR
				`notificationsQueue`.`responseCode` BETWEEN 400 AND 599
			)
			AND `notificationsQueue`.`retryCount` < :maxRetryCount
			FOR UPDATE
		");

		$this->updateNotificationQuery = $this->pdo->prepare("
			UPDATE 	`notificationsQueue`
			SET 	`responseCode` 				= :HTTPCode,
					`retryCount` 				= :retryCount,
					`lastDeliveryAttemptTime`	= STR_TO_DATE(:lastDeliveryAttemptTime, '".parent::dateTimeDBFormat."')
			WHERE 	`id` = :id;
		");

	}

	public function getPendingNotifications(int $maxRetryCount){
		if ($maxRetryCount < 1){
			throw new \LogicException("Incorrect maxRetryCount value ($maxRetryCount).");
		}

		$args = array(
			':maxRetryCount' => $maxRetryCount
		);

		return $this->execute(
			$this->getPendingNotificationsQuery,
			$args,
			\QueryTraits\Type::Read(),
			\QueryTraits\Approach::Many()
		);
	}

	public function updateNotification(Notification $notification){
		if($notification->getId() === null){
			throw new \LogicException("Updating a notification with an empty id");
		}
		
		$args = array(
			':id'						=> $notification->getId(),
			':HTTPCode'					=> $notification->getResponseCode(),
			':retryCount'				=> $notification->getRetryCount(),
			':lastDeliveryAttemptTime'	=> $notification->getLastDeliveryAttemptTime()->format(parent::dateTimeAppFormat)
		);

		$this->execute(
			$this->updateNotificationQuery,
			$args,
			\QueryTraits\Type::Write(),
			\QueryTraits\Approach::One()
		);
	}
}
