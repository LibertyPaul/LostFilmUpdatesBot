UPDATE `APICommands`
SET		`description` = 'Зарегистрироваться'
WHERE	`API` = 'TelegramAPI'
AND		`text` = '/start';

UPDATE `APICommands`
SET		`description` = 'Добавить уведомления о сериале'
WHERE	`API` = 'TelegramAPI'
AND		`text` = '/add_show';

UPDATE `APICommands`
SET		`description` = 'Удалить уведомления о сериале'
WHERE	`API` = 'TelegramAPI'
AND		`text` = '/remove_show';

UPDATE `APICommands`
SET		`description` = 'Показать выбранные сериалы'
WHERE	`API` = 'TelegramAPI'
AND		`text` = '/get_my_shows';

UPDATE `APICommands`
SET		`description` = 'Выключить уведомления'
WHERE	`API` = 'TelegramAPI'
AND		`text` = '/mute';

UPDATE `APICommands`
SET		`description` = 'Отменить команду'
WHERE	`API` = 'TelegramAPI'
AND		`text` = '/cancel';

UPDATE `APICommands`
SET		`description` = 'Показать инфо о боте'
WHERE	`API` = 'TelegramAPI'
AND		`text` = '/help';

UPDATE `APICommands`
SET		`description` = 'Про обход блокировок'
WHERE	`API` = 'TelegramAPI'
AND		`text` = '/about_tor';

UPDATE `APICommands`
SET		`description` = 'Задонатить пару баксов на доширак создателю'
WHERE	`API` = 'TelegramAPI'
AND		`text` = '/donate';

UPDATE `APICommands`
SET		`description` = 'Поделиться контактом бота'
WHERE	`API` = 'TelegramAPI'
AND		`text` = '/share';

UPDATE `APICommands`
SET		`description` = 'Удалиться из контакт-листа бота'
WHERE	`API` = 'TelegramAPI'
AND		`text` = '/stop';
