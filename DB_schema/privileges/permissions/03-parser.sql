REVOKE ALL PRIVILEGES					ON `&&db_name`.*			FROM '&&parser_db_user'@'&&parser_db_host';

GRANT LOCK TABLES						ON `&&db_name`.*			TO '&&parser_db_user'@'&&parser_db_host';
GRANT SELECT							ON `&&db_name`.`config`		TO '&&parser_db_user'@'&&parser_db_host';
GRANT SELECT							ON `&&db_name`.`APIs`		TO '&&parser_db_user'@'&&parser_db_host';
GRANT SELECT, INSERT, UPDATE			ON `&&db_name`.`series`		TO '&&parser_db_user'@'&&parser_db_host';
GRANT SELECT, INSERT, UPDATE, DELETE	ON `&&db_name`.`shows`		TO '&&parser_db_user'@'&&parser_db_host';
