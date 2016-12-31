CREATE TABLE `shows` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title_ru` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `title_en` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `onAir` enum('Y','N') NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `title_en` (`title_en`),
  UNIQUE KEY `title_ru` (`title_ru`),
  KEY `onAir` (`onAir`),
  FULLTEXT KEY `fulltext_all` (`title_ru`,`title_en`)
) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8;
