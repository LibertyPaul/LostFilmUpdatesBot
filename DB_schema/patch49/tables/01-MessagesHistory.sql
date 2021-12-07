ALTER TABLE `messagesHistory` 
	ADD COLUMN `coreCommandId` INT UNSIGNED NULL AFTER `text`,
	ADD INDEX `fk_messagesHistory_ccid` (`coreCommandId` ASC),
	ADD CONSTRAINT `messagesHistory-coreCommand`
		FOREIGN KEY (`coreCommandId`)
		REFERENCES `coreCommands`(`id`)
		ON DELETE SET NULL
		ON UPDATE CASCADE;
