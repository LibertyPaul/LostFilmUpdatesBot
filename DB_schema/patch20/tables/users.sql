ALTER TABLE `users`
	ADD `deleted`
		ENUM('Y', 'N')
		CHARACTER SET ASCII
		COLLATE ascii_general_ci
		NOT NULL
		DEFAULT 'N'
		AFTER `API`,
	ADD INDEX (`deleted`);
