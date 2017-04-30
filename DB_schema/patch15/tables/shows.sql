ALTER TABLE `shows`
	ADD `firstAppearanceTime` DATETIME
		NULL
		DEFAULT NULL,
	ADD `lastAppearanceTime` DATETIME
		NULL
		DEFAULT NULL;

UPDATE `shows`
SET `firstAppearanceTime`	= NOW(),
	`lastAppearanceTime`	= NOW();

ALTER TABLE `shows`
	CHANGE `firstAppearanceTime`
		`firstAppearanceTime` DATETIME
		NOT NULL,
	CHANGE `lastAppearanceTime`
		`lastAppearanceTime` DATETIME
		NOT NULL;

	
