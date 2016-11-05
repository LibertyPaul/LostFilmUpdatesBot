--
-- Table structure for table `series`
--

CREATE TABLE IF NOT EXISTS `series` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `show_id` int(10) unsigned NOT NULL,
  `seasonNumber` int(10) unsigned NOT NULL,
  `seriesNumber` int(10) unsigned NOT NULL,
  `title_ru` text COLLATE utf8_bin,
  `title_en` text COLLATE utf8_bin,
  PRIMARY KEY (`id`),
  UNIQUE KEY `show-season-series` (`show_id`,`seasonNumber`,`seriesNumber`),
  KEY `show_id` (`show_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=10 ;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `series`
--
ALTER TABLE `series`
  ADD CONSTRAINT `series_ibfk_1` FOREIGN KEY (`show_id`) REFERENCES `shows` (`id`);
  

DELIMITER //

CREATE TRIGGER seriesDispatch 
AFTER INSERT ON series FOR EACH ROW
BEGIN
	INSERT INTO `notificationsQueue` (series_id, user_id)
	SELECT NEW.id, `user_id` FROM `tracks` WHERE `show_id` = NEW.show_id;
END; //

DELIMITER ;

  

--
-- Table structure for table `notificationsQueue`
--

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

--
-- Constraints for dumped tables
--

--
-- Constraints for table `notificationsQueue`
--
ALTER TABLE `notificationsQueue`
  ADD CONSTRAINT `notificationsQueue_ibfk_1` FOREIGN KEY (`series_id`) REFERENCES `series` (`id`),
  ADD CONSTRAINT `notificationsQueue_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;




-- shows table improvements
ALTER TABLE  `shows` CHANGE  `title_ru`  `title_ru` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL ,
CHANGE  `title_en`  `title_en` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL;

ALTER TABLE  `shows` DROP  `seasonNumber` ,
DROP  `seriesNumber` ;

ALTER TABLE  `shows` CHANGE  `onAir`  `onAir` ENUM(  'Y',  'N' ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL;




















