ALTER TABLE `users`
	ADD `mute_`
		ENUM('Y', 'N') 
		NOT NULL
		DEFAULT 'N'
		AFTER `mute`,
	ADD	INDEX(`mute_`);

UPDATE users SET mute_ = (
	CASE
		WHEN mute = 1 THEN 'Y'
		WHEN mute = 0 THEN 'N'
	END
);

ALTER TABLE `users`
	DROP `mute`;

ALTER TABLE `users`
	CHANGE `mute_` 
	`mute`
	ENUM('Y', 'N')
	CHARACTER SET utf8
	COLLATE utf8_general_ci 
	NOT NULL 
	DEFAULT 'N';

ALTER TABLE `users`
	DROP INDEX `mute_`,
	ADD INDEX `mute`(`mute`);
