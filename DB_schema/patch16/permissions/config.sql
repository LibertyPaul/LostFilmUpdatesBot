GRANT
	SELECT
	ON `&&db_name`.`config`
	TO '&&bot_username'@'localhost';

GRANT
	SELECT
	ON `&&db_name`.`config`
	TO '&&parser_username'@'localhost';

GRANT
	ALL
	ON `&&db_name`.`config`
	TO '&&owner_username'@'localhost';
