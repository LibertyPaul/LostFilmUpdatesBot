DELIMITER //

DROP PROCEDURE IF EXISTS addShowIfNotExist;

CREATE PROCEDURE addShowIfNotExist(
	IN showTitleRu VARCHAR(255),
	IN showTitleEn VARCHAR(255),
	IN isOnAir CHAR
)
BEGIN
	DECLARE isExist BOOL;
	
	IF isOnAir != 'N' AND isOnAir != 'Y' THEN
		SIGNAL SQLSTATE '03000';
	END IF;


	SET isExist := (
		SELECT 	COUNT(*)
		FROM 	`shows`
		WHERE 	`title_ru` LIKE showTitleRu
		AND		`title_en` LIKE showTitleEn
	);

	IF isExist = 0 THEN
		INSERT INTO `shows` (title_ru, title_en, onAir)
		VALUES (showTitleRu, showTitleEn, isOnAir);
	END IF;
END;
//

DELIMITER ;
