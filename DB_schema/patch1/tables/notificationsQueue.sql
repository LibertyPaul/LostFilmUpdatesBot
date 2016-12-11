CREATE TABLE `notificationsQueue` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `series_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `responceCode` smallint(6) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `series_id_2` (`series_id`,`user_id`),
  KEY `responceCode` (`responceCode`),
  KEY `series_id` (`series_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;
