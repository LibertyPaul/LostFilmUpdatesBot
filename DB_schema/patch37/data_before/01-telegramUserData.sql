DELETE FROM `notificationsQueue`
WHERE `user_id` IN (
	SELECT `user_id`
	FROM `telegramUserData`
	WHERE `first_name` IS NULL
);

DELETE FROM `messagesHistory`
WHERE `user_id` IN (
	SELECT `user_id`
	FROM `telegramUserData`
	WHERE `first_name` IS NULL
);

DELETE FROM `tracks`
WHERE `user_id` IN (
	SELECT `user_id`
	FROM `telegramUserData`
	WHERE `first_name` IS NULL
);

DELETE FROM `users`
WHERE `id` IN (
	SELECT `user_id`
	FROM `telegramUserData`
	WHERE `first_name` IS NULL
);

DELETE FROM `telegramUserData`
WHERE `first_name` IS NULL;
