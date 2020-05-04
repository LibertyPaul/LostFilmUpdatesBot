DELETE FROM `config`
WHERE `section` = 'TelegramAPI'
AND `item` IN ('Message Resend Enabled', 'Message Resend URL');
