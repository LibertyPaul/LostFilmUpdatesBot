CREATE TABLE IF NOT EXISTS `messagesHistory`(
	`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
	`direction` enum('INCOMING','OUTGOING') NOT NULL,
	`chat_id` int(10) unsigned NOT NULL,
	`text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
	`inResponseTo` int(10) unsigned DEFAULT NULL,
	
	PRIMARY KEY (`id`),
	KEY `direction` (`direction`),
	KEY `user_id` (`user_id`),
	KEY `inResponseTo` (`inResponseTo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;
