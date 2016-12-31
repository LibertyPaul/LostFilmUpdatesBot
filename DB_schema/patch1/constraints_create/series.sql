ALTER TABLE `series`
	ADD
		CONSTRAINT `series_ibfk_1`
		FOREIGN KEY (`show_id`)
		REFERENCES `shows` (`id`);
  
