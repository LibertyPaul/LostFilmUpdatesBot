DROP TRIGGER IF EXISTS seriesDispatch;

DELIMITER //

CREATE TRIGGER seriesDispatch 
AFTER INSERT ON series FOR EACH ROW
BEGIN

	INSERT INTO `notificationsQueue` (series_id, user_id)
	SELECT NEW.id, `tracks`.`user_id`
	FROM `tracks`
	JOIN `users` ON `tracks`.`user_id` = `users`.`id`
	WHERE 	`show_id` = NEW.show_id
	AND		`users`.`mute` = 'N';
	

END; //

DELIMITER ;
