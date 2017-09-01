UPDATE `config`
SET `section` = 'TelegramAPI', `item` = 'Webhook URL'
WHERE `section` = 'Webhook' AND `item` = 'URL';

UPDATE `config`
SET `section` = 'TelegramAPI', `item` = 'Webhook Password'
WHERE `section` = 'Webhook' AND `item` = 'Password';
