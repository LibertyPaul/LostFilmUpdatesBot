<?php

namespace DAL;

require_once(__DIR__.'/../DAOBuilderInterface.php');
require_once(__DIR__.'/Notification.php');

class NotificationBuilder implements DAOBuilderInterface{

	public function buildObjectFromRow(array $row, string $dateTimeFormat){
		$notification = new Notification(
			intval($row['id']),
			intval($row['series_id']),
			intval($row['user_id']),
			intval($row['responseCode']),
			intval($row['retryCount']),
			\DateTimeImmutable::createFromFormat($dateTimeFormat, $row['lastDeliveryAttemptTimeStr'])
		);

		return $notification;
	}

}
