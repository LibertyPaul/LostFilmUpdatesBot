CREATE TABLE `ErrorYard`(
	`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`firstAppearanceTime` DATETIME NOT NULL,
	`lastAppearanceTime` DATETIME NOT NULL,
	`count` INT UNSIGNED NOT NULL,
	`errorId` INT UNSIGNED NOT NULL,
	PRIMARY KEY (`id`),
	INDEX `idx1` (`errorId` ASC, `firstAppearanceTime` ASC),
	CONSTRAINT `fk1`
		FOREIGN KEY (`errorId`)
		REFERENCES `ErrorDictionary` (`id`)
		ON DELETE RESTRICT
		ON UPDATE RESTRICT
)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8mb4
COLLATE = utf8mb4_bin;

