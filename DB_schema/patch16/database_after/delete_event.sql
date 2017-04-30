CREATE EVENT notificationsQueuePurge
ON SCHEDULE EVERY 1 DAY
DO
DELETE
FROM `notificationsQueue`
WHERE `lastDeliveryAttemptTime` <= DATE_SUB(
	NOW(),
	INTERVAL(
		SELECT TIME_FORMAT(`value`, '%h')
		FROM 	`config`
		WHERE	`section`	= 'notificationsQueue'
		AND		`item`		= 'message storage hours'
	)
	HOUR
);
