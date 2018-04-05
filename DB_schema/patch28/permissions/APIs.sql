GRANT
	SELECT,
	INSERT,
	UPDATE,
	DELETE
	ON `&&db_name`.`APIs`
	TO '&&owner_db_user'@'&&owner_db_host';

GRANT
	SELECT
	ON `&&db_name`.`APIs`
	TO '&&bot_db_user'@'&&bot_db_host';

GRANT
	SELECT
	ON `&&db_name`.`APIs`
	TO '&&parser_db_user'@'&&parser_db_host';
