<?php

namespace DAL;

require_once(__DIR__.'/../DAOBuilderInterface.php');
require_once(__DIR__.'/Series.php');

class SeriesBuilder implements DAOBuilderInterface{

	public function buildObjectFromRow(array $row, string $dateTimeFormat){
		$series = new Series(
			intval($row['id']),
			\DateTimeImmutable::createFromFormat($dateTimeFormat, $row['firstSeenAtStr']),
			intval($row['show_id']),
			intval($row['seasonNumber']),
			intval($row['seriesNumber']),
			$row['title_ru'],
			$row['title_en'],
			$row['ready'] === 'Y',
			$row['suggestedURL']
		);

		return $series;
	}

}
