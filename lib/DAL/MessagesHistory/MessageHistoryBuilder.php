<?php

namespace DAL;

require_once(__DIR__.'/../DAOBuilderInterface.php');
require_once(__DIR__.'/MessageHistory.php');

class MessageHistoryBuilder implements DAOBuilderInterface{

	public function buildObjectFromRow(array $row, string $dateTimeFormat){
		if($row['update_id'] !== null){
			$updateID = intval($row['update_id']);
		}
		else{
			$updateID = null;
		}

		if($row['inResponseTo'] !== null){
			$inResponseTo = intval($row['inResponseTo']);
		}
		else{
			$inResponseTo = null;
		}

		if($row['statusCode'] !== null){
			$statusCode = intval($row['statusCode']);
		}
		else{
			$statusCode = null;
		}


		$messageHistory = new MessageHistory(
			intval($row['id']),
			\DateTimeImmutable::createFromFormat($dateTimeFormat, $row['timeStr']),
			$row['source'],
			intval($row['user_id']),
			$updateID,
			$row['text'],
			$inResponseTo,
			$statusCode
		);

		return $messageHistory;
	}

}
