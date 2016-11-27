CREATE TABLE IF NOT EXISTS `notificationsQueue` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `series_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `responceCode` smallint(6) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `series_id_2` (`series_id`,`user_id`),
  KEY `responceCode` (`responceCode`),
  KEY `series_id` (`series_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

ALTER TABLE `notificationsQueue`
  ADD CONSTRAINT `notificationsQueue_ibfk_1` FOREIGN KEY (`series_id`) REFERENCES `series` (`id`),
  ADD CONSTRAINT `notificationsQueue_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

