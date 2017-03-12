DROP FUNCTION IF EXISTS getShowId;
DROP PROCEDURE IF EXISTS mergeDuplicateIfExists;

DELIMITER //

CREATE FUNCTION getShowId(
	arg_title_ru VARCHAR(255)
)
RETURNS INT(10) UNSIGNED
BEGIN
	DECLARE result INT(10) UNSIGNED;

	DECLARE CONTINUE HANDLER
		FOR NOT FOUND
		SET result = NULL;

	SELECT `id`
	INTO result
	FROM `shows`
	WHERE `title_ru` = arg_title_ru;

	RETURN result;
END;
//


CREATE PROCEDURE mergeDuplicateIfExists(
	IN oldShowTitle_ru VARCHAR(255),
	IN newShowTitle_ru VARCHAR(255)
)
BEGIN

	DECLARE oldId INT(10) UNSIGNED;
	DECLARE newId INT(10) UNSIGNED;

	SET oldId := getShowId(oldShowTitle_ru);
	SET newId := getShowId(newShowTitle_ru);

	IF oldId != newId THEN
		CALL mergeShows(oldId, newId);
	END IF;
END;
//

DELIMITER ;
