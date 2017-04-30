TRUNCATE TABLE `messagesHistory`;

ALTER TABLE `messagesHistory`
	CHANGE `text`
		`text` VARCHAR(5000)
		CHARACTER SET utf8mb4
		COLLATE utf8mb4_bin
		NOT NULL,
	CHANGE `direction`
		`source` ENUM(
			'User',
			'UpdateHandler'
		)
		CHARACTER SET ASCII
		COLLATE ascii_general_ci
		NOT NULL,
	ADD `statusCode` SMALLINT UNSIGNED NULL
		DEFAULT NULL
		COMMENT 'Valid for case of outgoing messages',
	ADD INDEX (`statusCode`)
	ADD `update_id` INT(12) UNSIGNED NULL
		DEFAULT NULL
		COMMENT 'update_id from Telegram API Update. Valid when source = ''User''.'
		AFTER `chat_id` ,
	ADD UNIQUE (`update_id`);
