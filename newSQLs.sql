CREATE DEFINER=`LFUB_owner_dev`@`%` PROCEDURE `BuildShowTitleIndex`(show_id INT(10) UNSIGNED, title VARCHAR(255) CHARACTER SET utf8)
BEGIN
    DECLARE pos, gramSize, spaceAt, wordNumber INT;
    DECLARE prevChar CHAR(1) CHARACTER SET utf8 DEFAULT NULL;
    DECLARE currentWord, gram VARCHAR(255) CHARACTER SET utf8;
    
    SET gramSize = 3;
    SET pos = 1;
    SET wordNumber = 1;
    
	SET title = TRIM(title);
    
    parse_loop: LOOP
		SET currentWord = REGEXP_INSTR
    
    
    WHILE pos <= CHAR_LENGTH(title) - gramSize + 1 DO
		SET gram = SUBSTR(title, pos, gramSize);
        SET spaceAt = LOCATE(' ', gram);
        
        IF spaceAt > 0 THEN
			IF prevChar IS NULL OR prevChar = ' ' THEN
				SET gram = SUBSTR(gram, 1, spaceAt - 1);
			ELSE
				SET gram = NULL;
			END IF;
		END IF;

		IF gram IS NOT NULL THEN
			INSERT INTO `ShowTitleIndex` (`show_id`, `gram`) VALUES (show_id, gram);
		END IF;
        
        IF spaceAt = 0 THEN
			SET pos = pos + 1;
		ELSE
			SET pos = pos + spaceAt;
		END IF;
        
        SET prevChar = SUBSTR(title, pos - 1, 1);
	END WHILE;
END



CREATE DEFINER=`LFUB_owner_dev`@`%` PROCEDURE `RefreshAllShowTitleIndexes`()
BEGIN
	DECLARE done INT DEFAULT FALSE;
    DECLARE v_id INT(10) UNSIGNED;
    DECLARE v_title_ru, v_title_en VARCHAR(255) CHARACTER SET utf8;
	DECLARE curs CURSOR FOR SELECT `id`, `title_ru`, `title_en` FROM `shows`;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

	OPEN curs;
    DELETE FROM `ShowTitleIndex`;

	read_loop: LOOP
		FETCH curs INTO v_id, v_title_ru, v_title_en;
        IF done THEN
			LEAVE read_loop;
		END IF;

        CALL BuildShowTitleIndex(v_id, v_title_ru);
        CALL BuildShowTitleIndex(v_id, v_title_en);
	END LOOP;

    CLOSE curs;
END



CREATE DEFINER=`LFUB_owner_dev`@`%` FUNCTION `SPLIT_STR`(
	x VARCHAR(255) CHARACTER SET utf8,
	delim VARCHAR(12) CHARACTER SET utf8,
	pos INT
) RETURNS varchar(255) CHARSET latin1
    DETERMINISTIC
RETURN REPLACE(
	SUBSTRING(
		SUBSTRING_INDEX(x, delim, pos),
		LENGTH(
			SUBSTRING_INDEX(x, delim, pos -1)
		) + 1
	),
    delim,
    ''
)
