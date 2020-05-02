DROP TRIGGER IF EXISTS seriesDispatch;
DROP TRIGGER IF EXISTS seriesDispatchOnInsert;
DROP TRIGGER IF EXISTS seriesDispatchOnUpdate;

DELIMITER |

CREATE TRIGGER seriesDispatchOnInsert
AFTER INSERT ON series FOR EACH ROW
BEGIN

	IF NEW.ready = 'Y' THEN
		INSERT INTO `notificationsQueue` (series_id, user_id)
		SELECT	NEW.id, `tracks`.`user_id`
		FROM	`tracks`
		JOIN	`users` ON `tracks`.`user_id` = `users`.`id`
		WHERE 	`show_id` = NEW.show_id
		AND		`users`.`mute` = 'N';
	END IF;

END;
|

CREATE TRIGGER seriesDispatchOnUpdate
AFTER UPDATE ON series FOR EACH ROW
BEGIN

	IF OLD.ready = 'N' AND NEW.ready = 'Y' THEN
		INSERT INTO `notificationsQueue` (series_id, user_id)
		SELECT	NEW.id, `tracks`.`user_id`
		FROM	`tracks`
		JOIN	`users` ON `tracks`.`user_id` = `users`.`id`
		WHERE 	`show_id` = NEW.show_id
		AND		`users`.`mute` = 'N';
	END IF;

END;
|

DELIMITER ;
