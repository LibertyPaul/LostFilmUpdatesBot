DELETE FROM `config`
WHERE	`section`	= 'Notifications'
AND		`item`		= 'Include Tor Advice';

INSERT INTO `config` (`section`, `item`, `value`)
VALUES ('Notifications', 'Include Tor Advice', 'Y');

DELETE FROM `APICommands`
WHERE	`API`	= 'TelegramAPI'
AND		`text`	= '/about_tor';

DELETE FROM `coreCommands`
WHERE	`text`	= 'CoreCommnad::AboutTor';

INSERT INTO `coreCommands` (`text`)
VALUES ('CoreCommnad::AboutTor');

INSERT INTO `APICommands` (`API`, `text`, `coreCommandId`, `priority`)
SELECT 'TelegramAPI', '/about_tor', id, 0
FROM `coreCommands`
WHERE	`text` = 'CoreCommnad::AboutTor';
