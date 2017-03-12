DELETE FROM `tracks`
WHERE `show_id` IN (
	SELECT `id`
	FROM `shows`
	WHERE `alias` IS NULL
	AND `onAir` = 'N'
);

DELETE FROM `notificationsQueue`
WHERE `series_id` IN (
	SELECT `series`.`id`
	FROM `series`
	JOIN `shows` ON `series`.`show_id` = `shows`.`id`
	WHERE `shows`.`alias` IS NULL
	AND `shows`.`onAir` = 'N'
);

DELETE FROM `series`
WHERE `show_id` IN (
	SELECT `id`
	FROM `shows`
	WHERE `alias` IS NULL
	AND `onAir` = 'N'
);

DELETE FROM `shows`
WHERE `alias` IS NULL
AND `onAir` = 'N';
