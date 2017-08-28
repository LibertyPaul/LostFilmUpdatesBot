CREATE TABLE `KeyValueStorage` (
	`key` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
	`value` varchar(255) DEFAULT NULL,
	`keepUntil` timestamp NOT NULL,
	 PRIMARY KEY (`key`),
	 KEY `keepUntil` (`keepUntil`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

GRANT
	SELECT,
	UPDATE,
	INSERT,
	DELETE
	ON `KeyValueStorage`
	TO '&&bot_name'@'localhost';

GRANT ALL
	ON `KeyValueStorage`
	TO '&&bot_owner'@'localhost';
