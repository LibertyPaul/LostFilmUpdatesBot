ALTER TABLE `messagesHistory`
	ADD CONSTRAINT `messagesHistory_ibfk_1`
		FOREIGN KEY (`inResponseTo`)
		REFERENCES `messagesHistory` (`id`)
		ON DELETE CASCADE
		ON UPDATE CASCADE;
