ALTER TABLE `tracks`
	ADD CONSTRAINT `tracks_ibfk_8`
		FOREIGN KEY (`user_id`)
		REFERENCES `users` (`id`)
		ON DELETE CASCADE
		ON UPDATE CASCADE,

	ADD CONSTRAINT `tracks_ibfk_9`
		FOREIGN KEY (`show_id`)
		REFERENCES `shows` (`id`)
		ON DELETE CASCADE
		ON UPDATE CASCADE;
