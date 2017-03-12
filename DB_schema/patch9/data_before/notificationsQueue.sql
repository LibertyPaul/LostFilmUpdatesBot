DELETE FROM `notificationsQueue`
WHERE `lastDeliveryAttemptTime` IS NULL
AND `responseCode` IS NOT NULL;
