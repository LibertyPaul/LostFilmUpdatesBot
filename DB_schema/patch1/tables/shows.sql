ALTER TABLE `shows`
	CHANGE `title_ru`
		`title_ru` VARCHAR(255)
		CHARACTER SET utf8
		COLLATE utf8_bin
		NOT NULL,
	CHANGE `title_en`
		`title_en` VARCHAR(255)
		CHARACTER SET utf8
		COLLATE utf8_bin
		NOT NULL;

ALTER TABLE `shows`
	ADD
		`onAir_` ENUM('Y', 'N')
		NOT NULL
		AFTER `onAir`;

UPDATE `shows` SET onAir_ = (
    CASE
        WHEN onAir = 0 THEN 'N'
        WHEN onAir = 1 THEN 'Y'
    END
);

ALTER TABLE `shows`
	DROP onAir;

ALTER TABLE `shows`
	CHANGE `onAir_`
	`onAir` ENUM('Y', 'N')
	CHARACTER SET utf8
	COLLATE utf8_general_ci
	NOT NULL;
