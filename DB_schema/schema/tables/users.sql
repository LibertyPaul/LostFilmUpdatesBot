CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `telegram_id` int(10) unsigned NOT NULL,
  `mute` ENUM('Y', 'N') NOT NULL DEFAULT 'N',
  `registration_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `telegram_username` varchar(255) DEFAULT NULL,
  `telegram_firstName` varchar(255) DEFAULT NULL,
  `telegram_lastName` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `telegram_id` (`telegram_id`)
) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8;
