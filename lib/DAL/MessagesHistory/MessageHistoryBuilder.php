<?php

namespace DAL;

require_once(__DIR__.'/../DAOBuilderInterface.php');
require_once(__DIR__.'/MessageHistory.php');

class MessageHistoryBuilder implements DAOBuilderInterface{

	public function buildObjectFromRow(array $row, string $dateTimeFormat){
		$messageHistory = new MessageHistory(
			intval($row['id']),
			\DateTimeImmutable::createFromFormat($dateTimeFormat, $row['createdStr']),
			$row['source'],
			intval($row['user_id']),
			intval($row['update_id']),
			$row['text'],
			intval($row['inResponseTo']),
			intval($row['statusCode'])
		);

		return $messageHistory;
	}

}
