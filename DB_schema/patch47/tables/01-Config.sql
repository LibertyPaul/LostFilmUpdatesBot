ALTER TABLE `config` 
	CHANGE COLUMN `value`
	`value` VARCHAR(255)
	CHARACTER SET 'utf8mb4'
	COLLATE 'utf8mb4_bin'
	NULL;
