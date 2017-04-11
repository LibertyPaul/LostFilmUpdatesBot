DROP PROCEDURE IF EXISTS setValues;
DROP FUNCTION IF EXISTS getValues;

DELIMITER //

CREATE PROCEDURE setValues(
	set_section VARCHAR(255),
	set_item VARCHAR(255),
	set_value VARCHAR(255)
)
BEGIN
 
	INSERT INTO `config` (`section`, `item`, `value`)
	VALUES (set_section, set_item, set_value);

END;
//


CREATE FUNCTION getValues(
	get_section VARCHAR(255),
	get_item VARCHAR(255)
)
RETURNS VARCHAR(255)
BEGIN
	DECLARE result VARCHAR(255)

	DECLARE CONTINUE HANDLER
		FOR NOT FOUND
		SET result = NULL;

	SELECT `value`
	INTO result
	FROM `config`
	WHERE `section` = get_section  AND `item` = get_item

	RETURN result;
END;
//

DELIMITER ;
