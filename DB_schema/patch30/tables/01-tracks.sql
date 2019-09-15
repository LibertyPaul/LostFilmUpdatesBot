ALTER TABLE `tracks`
	ADD COLUMN `created`
		DATETIME NULL
		DEFAULT CURRENT_TIMESTAMP
		AFTER `show_id`;

UPDATE `tracks` SET `created` = NULL;
