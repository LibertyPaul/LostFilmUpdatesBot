<?php

namespace DAL;

require_once(__DIR__.'/../DAOBuilderInterface.php');
require_once(__DIR__.'/Track.php');

class TrackBuilder implements DAOBuilderInterface{

	public function buildObjectFromRow(array $row, string $dateTimeFormat){
		if($row['createdStr'] !== null){
			$created = \DateTimeImmutable::createFromFormat($dateTimeFormat, $row['createdStr']);
		}
		else{
			$created = null;
		}

		$track = new Track(
			intval($row['user_id']),
			intval($row['show_id']),
			$created
		);

		return $track;
	}

}
