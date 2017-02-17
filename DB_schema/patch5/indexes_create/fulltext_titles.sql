ALTER TABLE `shows`
	ADD FULLTEXT `fulltext_titles`(
		`title_ru`,
		`title_en`
	);
