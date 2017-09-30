CREATE TABLE IF NOT EXISTS `telegramUserData` (
	`telegram_id`	INT(15) NOT NULL,
	`username`		VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
	`first_name`	VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
	`last_name`		VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
	 PRIMARY KEY (`telegram_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `users`
	ADD INDEX (`telegram_id`);

ALTER TABLE `telegramUserData`
	ADD CONSTRAINT `APIIdentifier_ibfk_1`
		FOREIGN KEY (`telegram_id`)
		REFERENCES `&&db_name`.`users`(`telegram_id`)
		ON DELETE CASCADE
		ON UPDATE CASCADE;


INSERT INTO `telegramUserData` (
	`telegram_id`,
	`username`,
	`first_name`,
	`last_name`
)
SELECT 
	`telegram_id`,
	`telegram_username`,
	`telegram_firstName`,
	`telegram_lastName`
FROM `users`;


ALTER TABLE `users`
	DROP `telegram_username`,
	DROP `telegram_firstName`,
	DROP `telegram_lastName`;
