REVOKE ALL PRIVILEGES					ON `&&db_name`.*					FROM '&&bot_db_user'@'&&bot_db_host';

GRANT LOCK TABLES						ON `&&db_name`.*					TO '&&bot_db_user'@'&&bot_db_host';
GRANT SELECT, INSERT, UPDATE, DELETE	ON `&&db_name`.`KeyValueStorage`	TO '&&bot_db_user'@'&&bot_db_host';
GRANT SELECT							ON `&&db_name`.`APIs`				TO '&&bot_db_user'@'&&bot_db_host';
GRANT SELECT, INSERT, UPDATE 			ON `&&db_name`.`messagesHistory`	TO '&&bot_db_user'@'&&bot_db_host';
GRANT SELECT, INSERT, UPDATE, DELETE	ON `&&db_name`.`users`				TO '&&bot_db_user'@'&&bot_db_host';
GRANT SELECT, INSERT, UPDATE, DELETE	ON `&&db_name`.`telegramUserData`	TO '&&bot_db_user'@'&&bot_db_host';
GRANT SELECT, UPDATE					ON `&&db_name`.`notificationsQueue`	TO '&&bot_db_user'@'&&bot_db_host';
GRANT SELECT							ON `&&db_name`.`shows`				TO '&&bot_db_user'@'&&bot_db_host';
GRANT SELECT							ON `&&db_name`.`coreCommands`		TO '&&bot_db_user'@'&&bot_db_host';
GRANT SELECT							ON `&&db_name`.`series`				TO '&&bot_db_user'@'&&bot_db_host';
GRANT SELECT							ON `&&db_name`.`APICommands`		TO '&&bot_db_user'@'&&bot_db_host';
GRANT SELECT							ON `&&db_name`.`config`				TO '&&bot_db_user'@'&&bot_db_host';
GRANT SELECT, INSERT, UPDATE, DELETE	ON `&&db_name`.`tracks`				TO '&&bot_db_user'@'&&bot_db_host';
GRANT SELECT, INSERT, UPDATE, DELETE	ON `&&db_name`.`ErrorDictionary`	TO '&&bot_db_user'@'&&bot_db_host';
GRANT SELECT, INSERT, UPDATE, DELETE	ON `&&db_name`.`ErrorYard`			TO '&&bot_db_user'@'&&bot_db_host';
