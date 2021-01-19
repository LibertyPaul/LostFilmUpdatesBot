INSERT INTO `config` (`section`, `item`, `value`)
VALUES ('Admin Notifications', 'Error Yard Reports Enabled', 'N');

UPDATE `config`
SET
	`item` = 'Status Channel Id',
	`value` = NULL
WHERE `section` = 'Admin Notifications'
AND `item` = 'Admin Id';
