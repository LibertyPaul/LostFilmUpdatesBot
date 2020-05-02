<?php

namespace DAL;

require_once(__DIR__.'/../DAOBuilderInterface.php');
require_once(__DIR__.'/User.php');

class UserBuilder implements DAOBuilderInterface{

	public function buildObjectFromRow(array $row, string $dateTimeFormat){
		$user = new User(
			intval($row['id']),
			$row['API'],
			$row['deleted'] === 'Y',
			$row['mute'] === 'Y',
			\DateTimeImmutable::createFromFormat($dateTimeFormat, $row['registrationTimeStr'])
		);

		return $user;
	}

}
