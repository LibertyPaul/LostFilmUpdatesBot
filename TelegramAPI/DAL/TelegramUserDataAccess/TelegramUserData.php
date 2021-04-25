<?php

namespace DAL;

require_once(__DIR__.'/../../../lib/DAL/APIUserDataInterface/APIUserData.php');

class TelegramUserData implements APIUserData{
	private $userId;
	private $chatId;
	private $type;
	private $username;
	private $firstName;
	private $lastName;

	public function __construct(
		int $userId,
		int $chatId,
		string $type,
		?string $username,
		string $firstName,
		string $lastName = null
	){
		$this->userId = $userId;
		$this->chatId = $chatId;
		$this->setType($type);
		$this->username = $username;
		$this->firstName = $firstName;
		$this->lastName = $lastName;
	}

	public function getUserId(){
		return $this->userId;
	}

	public function setUserId(int $userId){
		if($this->userId !== null){
			throw new \LogicException("Changing user id for the second time [$this->userId] --> $userId.");
		}

		$this->userId = $userId;
	}

	public function getAPISpecificId(){
		return $this->chatId;
	}

	public function setAPISpecificId(int $newAPISpecificId){
		return $this->chatId = $newAPISpecificId;
	}

	public function getType(){
		return $this->type;
	}

	public function setType(string $type){
		switch($type){
		case 'private':
		case 'group':
		case 'supergroup':
			break;

		default:
			throw new \LogicException("Unknown Telegram chat type: [$type].");
		}

		$this->type = $type;
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
			sprintf('User ID:     [%d]', $this->getUserId())		.PHP_EOL.
			sprintf('Chat ID:     [%d]', $this->getAPISpecificId())	.PHP_EOL.
			sprintf('Type:        [%s]', $this->getType())			.PHP_EOL.
			sprintf('Username:    [%s]', $this->getUsername())		.PHP_EOL.
			sprintf('First Name:  [%s]', $this->getFirstName())		.PHP_EOL.
			sprintf('Last Name:   [%s]', $this->getLastName())		.PHP_EOL.
			'+++++++++++++++++++++++++++++++++++++++++++++++++';

		return $result;
	}
}
