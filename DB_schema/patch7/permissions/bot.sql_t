GRANT
	SELECT,
	INSERT,
	UPDATE,
	DELETE
	ON `&&db_name`.`users`
	TO '&&db_bot_username'@'localhost';

GRANT
	SELECT,
	INSERT,
	UPDATE,
	DELETE
	ON `&&db_name`.`tracks`
	TO '&&db_bot_username'@'localhost';

GRANT
	SELECT
	ON `&&db_name`.`shows`
	TO '&&db_bot_username'@'localhost';

GRANT
	SELECT,
	UPDATE
	ON `&&db_name`.`notificationsQueue`
	TO '&&db_bot_username'@'localhost';


GRANT
	LOCK TABLES
	ON `&&db_name`.*
	TO '&&db_bot_username'@'localhost';


GRANT
	EXECUTE
	ON PROCEDURE `&&db_name`.`notificationDeliveryResult`
	TO '&&db_bot_username'@'localhost';
