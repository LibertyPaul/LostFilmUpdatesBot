<?php

namespace DAL;

require_once(__DIR__.'/../../../lib/DAL/APIUserDataInterface/APIUserDataBuilder.php');
require_once(__DIR__.'/TelegramUserData.php');

class TelegramUserDataBuilder implements APIUserDataBuilder {

	public function buildObjectFromRow(array $row, string $dateTimeFormat){
		$telegramUserData = new TelegramUserData(
			intval($row['user_id']),
			intval($row['chat_id']),
			$row['type'],
			$row['username'],
			$row['first_name'],
			$row['last_name']
		);

		return $telegramUserData;
	}

}

