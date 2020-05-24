ALTER TABLE `APICommands`
	ADD UNIQUE INDEX `API-Command`
	(`API` ASC, `text` ASC, `coreCommandId` ASC);

