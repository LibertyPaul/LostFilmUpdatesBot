ALTER TABLE `shows` 
	CHANGE COLUMN `alias`
		`alias` VARCHAR(255)
		CHARACTER SET 'utf8mb4'
		COLLATE 'utf8mb4_bin'
		NOT NULL,

	CHANGE COLUMN `title_ru`
		`title_ru` VARCHAR(255)
		CHARACTER SET 'utf8mb4'
		COLLATE 'utf8mb4_bin'
		NOT NULL,

	CHANGE COLUMN `title_en`
		`title_en` VARCHAR(255)
		CHARACTER SET 'utf8mb4'
		COLLATE 'utf8mb4_bin'
		NOT NULL,

	CHANGE COLUMN `onAir`
		`onAir` ENUM('Y', 'N')
		CHARACTER SET 'ascii'
		COLLATE 'ascii_bin'
		NOT NULL;

