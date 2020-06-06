ALTER TABLE `shows` 
	CHANGE COLUMN `title_ru`
		`title_ru` VARCHAR(255)
		CHARACTER SET 'utf8mb4'
		COLLATE 'utf8mb4_unicode_ci'
		NOT NULL,
	CHANGE COLUMN `title_en`
		`title_en` VARCHAR(255)
		CHARACTER SET 'utf8mb4'
		COLLATE 'utf8mb4_unicode_ci'
		NOT NULL;
