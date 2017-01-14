DELIMITER //

DROP PROCEDURE IF EXISTS notificationDeliveryResult;

CREATE PROCEDURE notificationDeliveryResult(
	IN notificationId INT(10) UNSIGNED,
	IN HTTPCode SMALLINT
)
BEGIN
	UPDATE 	`notificationsQueue`
	SET 	`responseCode` 				= HTTPCode,
			`retryCount` 				= `retryCount` + 1,
			`lastDeliveryAttemptTime`	= NOW()
	WHERE 	`id` = notificationId;
END;
//

DELIMITER ;
