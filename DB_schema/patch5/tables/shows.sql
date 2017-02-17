ALTER TABLE `shows`
	ADD `alias`
	VARCHAR(255)
	CHARACTER SET utf8
	COLLATE utf8_general_ci
	NULL DEFAULT NULL
	AFTER `id`;

ALTER TABLE `shows`
	CHANGE `title_ru`
	`title_ru` VARCHAR(255)
	 CHARACTER SET utf8
	 COLLATE utf8_general_ci
	 NOT NULL;

ALTER TABLE `shows`
	CHANGE `title_en`
	`title_en` VARCHAR(255)
	CHARACTER SET utf8
	COLLATE utf8_general_ci
	NOT NULL;



UPDATE `shows` SET `title_en` = 'The 100' WHERE `title_ru` = 'Сотня';
UPDATE `shows` SET `title_en` = 'Stargate: Universe' WHERE `title_ru` = 'Звездные врата: Вселенная';
UPDATE `shows` SET `title_en` = 'Iron Fist' WHERE `title_ru` = 'Железный кулак';
UPDATE `shows` SET `title_en` = 'Luke Cage' WHERE `title_ru` = 'Люк Кейдж';
UPDATE `shows` SET `title_en` = 'Legends of Tomorrow' WHERE `title_ru` = 'Легенды завтрашнего дня';
UPDATE `shows` SET `title_en` = 'Agents of S.H.I.E.L.D.' WHERE `title_ru` = 'Агенты Щ.И.Т.';

