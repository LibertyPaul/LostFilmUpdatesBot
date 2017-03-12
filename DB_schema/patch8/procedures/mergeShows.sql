DROP PROCEDURE IF EXISTS mergeShows;

DELIMITER //

CREATE PROCEDURE mergeShows(
	IN oldShowId INT(10) UNSIGNED,
	IN newShowId INT(10) UNSIGNED
)
BEGIN
	
	DECLARE EXIT HANDLER FOR SQLEXCEPTION
	BEGIN
		ROLLBACK;
		SIGNAL SQLSTATE '03000';
	END;

	START TRANSACTION;

	-- Fix for `tracks`
	DELETE FROM `tracks`
	WHERE `show_id` = oldShowId
	AND `user_id` IN (
		SELECT `user_id` FROM (
			SELECT `user_id`
			FROM `tracks`
			WHERE `show_id` = newShowId
		) x
	);

	UPDATE	`tracks`
	SET		`show_id` = newShowId
	WHERE	`show_id` = oldShowId;

	-- Fix for `notificationsQueue`
	DELETE FROM `notificationsQueue`
	WHERE `series_id` IN (
		SELECT `t1`.`id`
		FROM `series` `t1`
		JOIN `series` `t2`
			ON	`t1`.`seriesNumber` = `t2`.`seriesNumber`
			AND	`t1`.`seasonNumber` = `t2`.`seasonNumber`
		WHERE	`t1`.`show_id` = oldShowId
		AND		`t2`.`show_id` = newShowId
	);

	-- Fix for series
	DELETE FROM `series`
	WHERE `id` IN (
		SELECT `id` FROM (
			SELECT `t1`.`id`
			FROM `series` `t1`
			JOIN `series` `t2`
				ON	`t1`.`seriesNumber` = `t2`.`seriesNumber`
				AND	`t1`.`seasonNumber` = `t2`.`seasonNumber`
			WHERE	`t1`.`show_id` = oldShowId
			AND		`t2`.`show_id` = newShowId
		) x
	);

	UPDATE	`series`
	SET		`show_id` = newShowId
	WHERE	`show_id` = oldShowId;

	DELETE FROM `shows`
	WHERE `id` = oldShowId;

	COMMIT;
END;
//

DELIMITER ;
