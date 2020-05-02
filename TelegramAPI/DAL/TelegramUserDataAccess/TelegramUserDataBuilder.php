<?php

namespace DAL;

require_once(__DIR__.'/../../../lib/DAL/DAOBuilderInterface.php');
require_once(__DIR__.'/TelegramUserData.php');

class TelegramUserDataBuilder implements DAOBuilderInterface{

	public function buildObjectFromRow(array $row, string $dateTimeFormat){
		$telegramUserData = new TelegramUserData(
			intval($row['user_id']),
			intval($row['telegram_id']),
			$row['username'],
			$row['first_name'],
			$row['last_name']
		);

		return $telegramUserData;
	}

}

