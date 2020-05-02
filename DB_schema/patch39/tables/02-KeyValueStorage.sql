ALTER TABLE `KeyValueStorage`
	CHANGE COLUMN `key`
		`key` VARCHAR(255)
		CHARACTER SET 'utf8mb4'
		COLLATE 'utf8mb4_bin'
		NOT NULL,

	CHANGE COLUMN `value`
		`value` VARCHAR(255)
		CHARACTER SET 'utf8mb4'
		COLLATE 'utf8mb4_bin'
		NULL
		DEFAULT NULL;

