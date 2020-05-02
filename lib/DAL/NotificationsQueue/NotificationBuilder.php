<?php

namespace DAL;

require_once(__DIR__.'/../DAOBuilderInterface.php');
require_once(__DIR__.'/Notification.php');

class NotificationBuilder implements DAOBuilderInterface{

	public function buildObjectFromRow(array $row, string $dateTimeFormat){
		if($row['lastDeliveryAttemptTimeStr'] !== null){
			$lastDeliveryAttemptTime = \DateTimeImmutable::createFromFormat($dateTimeFormat, $row['lastDeliveryAttemptTimeStr']);
			if($lastDeliveryAttemptTime === false){
				throw new \LogicException("Failed to convert DB Timestamp [$row[lastDeliveryAttemptTimeStr]].");
			}
		}
		else{
			$lastDeliveryAttemptTime = null;
		}

		if($row['responseCode'] !== null){
			$responseCode = intval($row['responseCode']);
		}
		else{
			$responseCode = null;
		}

		$notification = new Notification(
			intval($row['id']),
			intval($row['series_id']),
			intval($row['user_id']),
			$responseCode,
			intval($row['retryCount']),
			$lastDeliveryAttemptTime
		);

		return $notification;
	}

}
