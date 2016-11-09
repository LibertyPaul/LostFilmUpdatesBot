DELIMITER //

DROP PROCEDURE IF EXISTS addSeriesIfNotExist;

CREATE PROCEDURE addSeriesIfNotExist(
	IN showTitleRu VARCHAR(255),
	IN showTitleEn VARCHAR(255),
	IN season INT(10) UNSIGNED,
	IN series INT(10) UNSIGNED,
	IN seriesTitleRu TEXT,
	IN seriesTitleEn TEXT
)
BEGIN
	DECLARE showId INT(10) UNSIGNED;
	DECLARE seriesCount INT(10) UNSIGNED;
	
	SET showId := (
		SELECT	`id`
		FROM	`shows` 
		WHERE	`shows`.`title_ru`	LIKE showTitleRu 
		AND		`shows`.`title_en`	LIKE showTitleEn
	);
	
	IF (showId IS NULL) THEN
		SIGNAL SQLSTATE '02000';
	END IF;

	SET seriesCount := (
		SELECT COUNT(*)
		FROM	`series`
		WHERE 	`show_id`		= showId
		AND		`seasonNumber`	= season
		AND		`seriesNumber`	= series
	);

	IF seriesCount = 0 THEN
		INSERT INTO `series` (show_id, seasonNumber, seriesNumber, title_ru, title_en)
		VALUES (showId, season, series, seriesTitleRu, seriesTitleEn);
	END IF;
END;
//

DELIMITER ;

																				
