<?php

namespace DAL;

require_once(__DIR__.'/../../../lib/DAL/APIUserData/APIUserData.php');

class TelegramUserData implements APIUserData{
	private $userId;
	private $telegramId;
	private $username;
	private $firstName;
	private $lastName;

	private function __construct(
		int $userId,
		int $telegramId,
		string $username = null,
		string $firstName,
		string $lastName = null
	){
		$this->userId = $userId;
		$this->telegramId = $telegramId;
		$this->username = $username;
		$this->firstName = $firstName;
		$this->lastName = $lastName;
	}

	public function getUserId(){
		return $this->userId;
	}

	public function getAPISpecificId(){
		return $this->telegramId;
	}

	public function getUsername(){
		return $this->username;
	}

	public function getFirstName(){
		return $this->firstName;
	}

	public function getLastName(){
		return $this->lastName;
	}

	public function __toString(){
		$result =
			'+++++++++++++++[Telegram User Data]++++++++++++++'		.PHP_EOL.
			sprintf('User Id:     [%d]', $this->getUserId())		.PHP_EOL.
			sprintf('Telegram Id: [%d]', $this->getTelegramId())	.PHP_EOL.
			sprintf('Username:    [%s]', $this->getUsername())		.PHP_EOL.
			sprintf('First Name:  [%s]', $this->getFirstName())		.PHP_EOL.
			sprintf('Last Name:   [%s]', $this->getLastName())		.PHP_EOL.
			'+++++++++++++++++++++++++++++++++++++++++++++++++';

		return $result;
	}
}
