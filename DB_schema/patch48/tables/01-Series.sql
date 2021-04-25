ALTER TABLE `series` 
	ADD COLUMN `suggestedURL`
		VARCHAR(512)
		CHARACTER SET 'utf8mb4'
		COLLATE 'utf8mb4_bin'
		NULL
		AFTER `ready`,
	
	ADD UNIQUE INDEX `suggestedURL_UNIQUE`
		(`suggestedURL` ASC);
