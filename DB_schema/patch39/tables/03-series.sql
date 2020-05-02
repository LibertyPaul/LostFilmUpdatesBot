ALTER TABLE `series` 
	CHANGE COLUMN `title_ru`
		`title_ru` TEXT
		CHARACTER SET 'utf8mb4'
		COLLATE 'utf8mb4_bin'
		NULL
		DEFAULT NULL,

	CHANGE COLUMN `title_en`
		`title_en` TEXT
		CHARACTER SET 'utf8mb4'
		COLLATE 'utf8mb4_bin'
		NULL
		DEFAULT NULL;

