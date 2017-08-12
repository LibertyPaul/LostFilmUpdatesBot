ALTER TABLE `telegramUserData`
	DROP FOREIGN KEY `telegramUserData_ibfk_1`,
	DROP PRIMARY KEY;

ALTER TABLE `telegramUserData`
	ADD `user_id`
		INT(10) UNSIGNED
		FIRST;

UPDATE `telegramUserData` tud
SET `user_id` = (
	SELECT `id`
	FROM `users`
	WHERE `APIIdentifier` = tud.`telegram_id`
	AND `API` = 'TelegramAPI'
);

ALTER TABLE `users`
	DROP INDEX API;

ALTER TABLE `users`
	DROP `APIIdentifier`;

ALTER TABLE `telegramUserData`
	ADD PRIMARY KEY(`user_id`);

ALTER TABLE `telegramUserData`
	ADD FOREIGN KEY (`user_id`)
		REFERENCES `LostFilmUpdatesBot_dev`.`users`(`id`)
		ON DELETE CASCADE
		ON UPDATE CASCADE;



