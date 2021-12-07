UPDATE `messagesHistory` mh SET `coreCommandId` = (
	SELECT `coreCommandId` FROM `APICommands` ac
	WHERE mh.`text` = ac.`text`
	OR mh.`text` LIKE CONCAT(ac.`text`, '@%')
);
