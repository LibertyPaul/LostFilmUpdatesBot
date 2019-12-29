CREATE TABLE `ShowTitleIndex`(
	`id`			INT(10) 	UNSIGNED 								NOT NULL AUTO_INCREMENT,
	`show_id`		INT(10) 	UNSIGNED 								NOT NULL,
	`version_id`	INT(10) 	UNSIGNED 								NOT NULL,
	`gram`			CHAR(10)	CHARACTER SET utf8 COLLATE utf8_bin		NOT NULL,

	PRIMARY KEY (`id`),

	INDEX `fk_ShowTitleIndex_1_idx` (`version_id` ASC),
	INDEX `index3` (`show_id` ASC, `version_id` ASC, `gram` ASC),

	CONSTRAINT `fk_ShowTitleIndex_1`
		FOREIGN KEY (`version_id`)
		REFERENCES `ShowTitleIndexVersions`(`id`)
		ON DELETE CASCADE
		ON UPDATE CASCADE
);
