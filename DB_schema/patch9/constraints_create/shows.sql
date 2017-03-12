ALTER TABLE shows
	DROP INDEX alias;

ALTER TABLE shows
	ADD UNIQUE (`alias`);
	
