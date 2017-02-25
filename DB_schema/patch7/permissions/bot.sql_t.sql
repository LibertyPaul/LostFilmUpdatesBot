GRANT
	SELECT,
	INSERT,
	UPDATE,
	DELETE
	ON `LostFilmUpdatesBot_test`.`users`
	TO 'LFUB_bot_test'@'localhost';

GRANT
	SELECT,
	INSERT,
	UPDATE,
	DELETE
	ON `LostFilmUpdatesBot_test`.`tracks`
	TO 'LFUB_bot_test'@'localhost';

GRANT
	SELECT,
	UPDATE
	ON `LostFilmUpdatesBot_test`.`notificationsQueue`
	TO 'LFUB_bot_test'@'localhost';


GRANT
	LOCK TABLES
	ON `LostFilmUpdatesBot_test`.*
	TO 'LFUB_bot_test'@'localhost';


GRANT
	EXECUTE
	ON PROCEDURE `LostFilmUpdatesBot_test`.`notificationDeliveryResult`
	TO 'LFUB_bot_test'@'localhost';
