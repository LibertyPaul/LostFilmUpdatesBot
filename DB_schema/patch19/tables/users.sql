ALTER TABLE `users`
	ADD `API` ENUM('TelegramAPI', 'VKAPI')
		CHARACTER SET ASCII
		COLLATE ascii_bin
		NOT NULL
		DEFAULT 'TelegramAPI'
		AFTER  `id`,
	CHANGE `API`
		`API` ENUM('TelegramAPI', 'VKAPI')
		CHARACTER SET ASCII
		COLLATE ascii_bin
		NOT NULL,
	CHANGE `telegram_id`
		`APIIdentifier` INT(10) UNSIGNED
		NOT NULL,
	DROP INDEX `telegram_id`,
	ADD UNIQUE (`API`, `APIIdentifier`),
	ADD INDEX (`APIIdentifier`),
	CHANGE `mute`
		`mute` ENUM('Y', 'N')
		CHARACTER SET ASCII
		COLLATE ascii_general_ci
		NOT NULL
		DEFAULT 'N';
