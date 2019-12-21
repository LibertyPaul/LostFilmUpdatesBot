ALTER TABLE `APICommands` 
	ADD COLUMN `description` VARCHAR(1000)
	CHARACTER SET 'utf8mb4'
	NULL
	AFTER `priority`;

