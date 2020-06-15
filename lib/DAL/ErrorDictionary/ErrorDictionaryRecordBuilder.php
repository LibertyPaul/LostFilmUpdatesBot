<?php

namespace DAL;

require_once(__DIR__.'/../DAOBuilderInterface.php');
require_once(__DIR__.'/ErrorDictionaryRecord.php');

class ErrorDictionaryRecordBuilder implements DAOBuilderInterface{

	public function buildObjectFromRow(array $row, string $dateTimeFormat){
		$errorBucket = new ErrorDictionaryRecord(
			intval($row['id']),
			$row['level'],
			$row['source'],
			intval($row['line']),
			$row['fullText']
		);

		return $errorBucket;
	}

}
