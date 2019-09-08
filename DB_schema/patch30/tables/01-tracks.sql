ALTER TABLE `LostFilmUpdatesBot_dev`.`tracks`
	ADD COLUMN `created`
		DATETIME NULL
		DEFAULT CURRENT_TIMESTAMP
		AFTER `show_id`;

UPDATE `LostFilmUpdatesBot_dev`.`tracks` SET `created` = NULL;
