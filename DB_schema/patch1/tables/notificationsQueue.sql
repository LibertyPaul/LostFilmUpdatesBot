CREATE TABLE `notificationsQueue` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `series_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `responseCode` smallint(6) DEFAULT NULL,
  `retryCount` tinyint(3) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `series_id_2` (`series_id`,`user_id`),
  KEY `responseCode` (`responseCode`),
  KEY `series_id` (`series_id`),
  KEY `user_id` (`user_id`),
  KEY `retryCount` (`retryCount`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;
