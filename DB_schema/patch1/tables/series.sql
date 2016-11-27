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
