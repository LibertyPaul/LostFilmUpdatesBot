ALTER TABLE `messagesHistory`
	CHANGE `chat_id`
		`user_id`
		INT(10) UNSIGNED
		NOT NULL;


DELETE FROM `messagesHistory`
WHERE `user_id` NOT IN (
    SELECT `telegram_id` FROM `users`
);

UPDATE `messagesHistory` mH
SET `user_id` = ( 
    SELECT `users`.`id`
    FROM `users`
    WHERE `telegram_id` = mH.`user_id`
);


ALTER TABLE `messagesHistory`
	ADD INDEX(`user_id`),
	ADD FOREIGN KEY (`user_id`)
		REFERENCES `users`(`id`)
		ON DELETE CASCADE
		ON UPDATE CASCADE;
