ALTER TABLE `users`
	ADD `API` ENUM('TelegramAPI', 'VKAPI')
		CHARACTER SET ASCII
		COLLATE ascii_bin
		NOT NULL
		DEFAULT 'TelegramAPI'
		AFTER  `id`;

ALTER TABLE `users`
	CHANGE `API`
		`API` ENUM('TelegramAPI', 'VKAPI')
		CHARACTER SET ASCII
		COLLATE ascii_bin
		NOT NULL;

ALTER TABLE `users` 
	ADD COLUMN `APIIdentifier`
		INT(15) NOT NULL; 

UPDATE `users`
	SET `APIIdentifier` = `telegram_id`;

ALTER TABLE `telegramUserData`
	DROP FOREIGN KEY `APIIdentifier_ibfk_1`;

ALTER TABLE `users`
	ADD UNIQUE (`API`, `APIIdentifier`);
	    
ALTER TABLE `users`
	ADD INDEX (`APIIdentifier`);

ALTER TABLE `telegramUserData`
	ADD CONSTRAINT APIIdentifier_ibfk_1
		FOREIGN KEY (`telegram_id`)
		REFERENCES `users`(`APIIdentifier`);

ALTER TABLE `users` 
	DROP COLUMN `telegram_id`;

ALTER TABLE `users`
	CHANGE `mute`
		`mute` ENUM('Y', 'N')
		CHARACTER SET ASCII
		COLLATE ascii_general_ci
		NOT NULL
		DEFAULT 'N';
