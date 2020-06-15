<?php

namespace DAL;

require_once(__DIR__.'/../DAOBuilderInterface.php');
require_once(__DIR__.'/ErrorBucket.php');

class ErrorBucketBuilder implements DAOBuilderInterface{

	public function buildObjectFromRow(array $row, string $dateTimeFormat){
		$errorBucket = new ErrorBucket(
			intval($row['id']),
			\DateTimeImmutable::createFromFormat($dateTimeFormat, $row['firstAppearanceTimeStr']),
			\DateTimeImmutable::createFromFormat($dateTimeFormat, $row['lastAppearanceTimeStr']),
			intval($row['count']),
			intval($row['errorId'])
		);

		return $errorBucket;
	}

}
