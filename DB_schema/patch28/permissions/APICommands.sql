GRANT
	SELECT,
	INSERT,
	UPDATE,
	DELETE
	ON `&&db_name`.`APICommands`
	TO '&&owner_db_user'@'&&owner_db_host';

GRANT
	SELECT
	ON `&&db_name`.`APICommands`
	TO '&&bot_db_user'@'&&bot_db_host';
