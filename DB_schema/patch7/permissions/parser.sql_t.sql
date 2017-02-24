GRANT
	SELECT,
	INSERT,
	UPDATE,
	DELETE
	ON `LostFilmUpdatesBot_test`.`shows`
	TO 'LFUB_parser_test'@'localhost';

GRANT
	SELECT,
	INSERT
	ON `LostFilmUpdatesBot_test`.`series`
	TO 'LFUB_parser_test'@'localhost';

GRANT
	LOCK TABLES
	ON `LostFilmUpdatesBot_test`.*
	TO 'LFUB_parser_test'@'localhost';
