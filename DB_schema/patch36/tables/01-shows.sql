ALTER TABLE `shows` 
	CHANGE COLUMN `firstAppearanceTime`
		`firstAppearanceTime`
		DATETIME(6)
		NOT NULL;

ALTER TABLE `shows` 
	CHANGE COLUMN `lastAppearanceTime`
		`lastAppearanceTime`
		DATETIME(6)
		NOT NULL;
