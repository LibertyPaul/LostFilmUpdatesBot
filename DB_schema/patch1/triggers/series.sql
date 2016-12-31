DELIMITER //

CREATE TRIGGER seriesDispatch 
AFTER INSERT ON series FOR EACH ROW
BEGIN
	INSERT INTO `notificationsQueue` (series_id, user_id)
	SELECT NEW.id, `user_id` FROM `tracks` WHERE `show_id` = NEW.show_id;
END; //

DELIMITER ;
