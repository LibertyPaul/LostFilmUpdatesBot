ALTER TABLE `users`
	DEFAULT CHARACTER SET utf8mb4,

	MODIFY telegram_username VARCHAR(255)
	CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,

	MODIFY telegram_firstName VARCHAR(255)
	CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,

	MODIFY telegram_lastName VARCHAR(255)
	CHARACTER SET utf8mb4 COLLATE utf8mb4_bin;
