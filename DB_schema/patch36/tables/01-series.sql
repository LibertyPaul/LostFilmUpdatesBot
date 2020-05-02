ALTER TABLE `series` 
	CHANGE COLUMN `datetimestamp`
		`firstSeenAt`
		DATETIME(6)
		NOT NULL;

ALTER TABLE `series`
	ADD COLUMN `ready`
	ENUM('Y', 'N')
	NOT NULL;


UPDATE `series` SET `ready` = 'Y';
