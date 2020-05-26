ALTER TABLE `telegramUserData`
	CHANGE COLUMN `telegram_id`
		`chat_id` BIGINT
		NOT NULL,

	CHANGE COLUMN `first_name`
		`first_name` VARCHAR(255)
		CHARACTER SET 'utf8mb4'
		COLLATE 'utf8mb4_bin'
		NULL,

	ADD COLUMN `type`
		VARCHAR(20)
		CHARACTER SET 'ascii'
		COLLATE 'ascii_bin'
		NOT NULL
		AFTER `chat_id`;

UPDATE `telegramUserData` SET `type` = 'private';
