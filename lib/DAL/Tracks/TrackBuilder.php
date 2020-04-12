<?php

namespace DAL;

require_once(__DIR__.'/../DAOBuilderInterface.php');
require_once(__DIR__.'/Track.php');

class TrackBuilder implements DAOBuilderInterface{

	public function buildObjectFromRow(array $row, string $dateTimeFormat){
		$track = new Track(
			intval($row['user_id']),
			intval($row['show_id']),
			\DateTimeImmutable::createFromFormat($dateTimeFormat, $row['createdStr'])
		);

		return $track;
	}

}
