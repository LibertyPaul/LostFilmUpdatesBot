ALTER TABLE `messagesHistory` 
	CHANGE COLUMN `update_id`
		`external_id` INT NULL
		DEFAULT NULL
		COMMENT 'API-level unique message identifier. Valid when source = \'User\'.' ,

	ADD UNIQUE INDEX `external_id` (`external_id` ASC),
	DROP INDEX `update_id`;

