CREATE EVENT delete_record
ON SCHEDULE EVERY 1 DAY
DO
DELETE
FROM `notificationsQueue`
WHERE `lastDeliveryAttemptTime` <= DATE_SUB(
	NOW(),
	INTERVAL(
		SELECT (TIME_FORMAT(`value`, '%h')) 
		FROM 	`config` 
		WHERE	`section`	= 'notificationQueue_store' 
		AND		`item`		= 'notificationQueue_store_hours' 
	)
	HOUR
)
