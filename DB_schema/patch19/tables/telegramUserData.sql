CREATE TABLE IF NOT EXISTS `telegramUserData` (
	`telegram_id`	INT(15) NOT NULL,
	`username`		VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
	`first_name`	VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
	`last_name`		VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
	 PRIMARY KEY (`telegram_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `telegramUserData`
	ADD CONSTRAINT `telegramUserData_ibfk_1`
		FOREIGN KEY (`telegram_id`)
		REFERENCES `users` (`APIIdentifier`)
		ON DELETE CASCADE
		ON UPDATE CASCADE;


INSERT INTO `telegramUserData` (
	`telegram_id`,
	`username`,
	`first_name`,
	`last_name`
)
SELECT 
	`APIIdentifier`,
	`telegram_username`,
	`telegram_firstName`,
	`telegram_lastName`
FROM `users`
WHERE `API` = 'TelegramAPI';


ALTER TABLE `users`
	DROP `telegram_username`,
	DROP `telegram_firstName`,
	DROP `telegram_lastName`;
