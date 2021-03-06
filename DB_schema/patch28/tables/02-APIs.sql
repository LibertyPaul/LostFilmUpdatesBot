CREATE TABLE IF NOT EXISTS `APIs` (
	`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`name` VARCHAR(255) CHARSET 'ascii' COLLATE 'ascii_bin' NOT NULL,
	PRIMARY KEY (`id`),
	UNIQUE INDEX `APIName_UNQ` (`name` ASC)
);

INSERT INTO `APIs` (`name`)
VALUES ('TelegramAPI'),
             ('VKAPI'),
          ('ViberAPI');

ALTER TABLE `users`
	CHANGE `API`
		`API` VARCHAR(255) CHARSET 'ascii' COLLATE 'ascii_bin' NOT NULL,
	ADD INDEX(`API`),
	ADD CONSTRAINT `userAPI`
		FOREIGN KEY (`API`)
		REFERENCES `APIs` (`name`)
		ON DELETE RESTRICT
		ON UPDATE CASCADE;
