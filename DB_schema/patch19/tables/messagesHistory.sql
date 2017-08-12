ALTER TABLE `messagesHistory`
	CHANGE `chat_id`
		`user_id`
		INT(10) UNSIGNED
		NOT NULL;

ALTER TABLE `messagesHistory`
	ADD INDEX(`user_id`),
	ADD FOREIGN KEY (`user_id`)
		REFERENCES `users`(`id`)
		ON DELETE CASCADE
		ON UPDATE CASCADE;
