DELIMITER //

DROP PROCEDURE IF EXISTS addSeriesIfNotExist;

CREATE PROCEDURE addSeriesIfNotExist(
	IN showTitleRu VARCHAR(255),
	IN showTitleEn VARCHAR(255),
	IN seasonNumber INT(10) UNSIGNED,
	IN seriesNumber INT(10) UNSIGNED,
	IN seriesTitleRu TEXT,
	IN seriesTitleEn TEXT
)
proc_body:BEGIN
	DECLARE notFound INT DEFAULT FALSE;
	DECLARE show_id INT(10) UNSIGNED;
	DECLARE seriesCount INT(10) UNSIGNED;
	DECLARE c_shows CURSOR FOR
		SELECT	`id`
		FROM	`shows` 
		WHERE	`shows`.`title_ru`	LIKE showTitleRu 
		AND		`shows`.`title_en`	LIKE showTitleEn;
	DECLARE CONTINUE HANDLER FOR NOT FOUND SET notFound = TRUE;
	
	OPEN c_shows;
	FETCH c_shows INTO show_id;
	CLOSE c_shows;
	IF notFound THEN
		LEAVE proc_body;
	END IF;

	SET seriesCount = (
		SELECT COUNT(*)
		FROM	`series`
		WHERE 	`show_id`		= show_id
		AND		`seasonNumber`	= seasonNumber
		AND		`seriesNumber`	= seriesNumber
	);

	IF seriesCount > 0 THEN
		LEAVE proc_body;
	END IF;

	INSERT INTO `series` (show_id, seasonNumber, seriesNumber, title_ru, title_en)
	VALUES (show_id, seasonNumber, seriesNumber, seriesTitleRu, seriesTitleEn);
END;
//

DELIMITER ;

																				
