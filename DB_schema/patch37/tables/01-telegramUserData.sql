ALTER TABLE `telegramUserData` 
	CHANGE COLUMN `first_name`
		`first_name`
		VARCHAR(255)
		CHARACTER SET 'utf8mb4'
		COLLATE 'utf8mb4_bin'
		NOT NULL ;

