<?php

namespace DAL;

require_once(__DIR__.'/../../../lib/DAL/CommonAccess.php');
require_once(__DIR__.'/../../../lib/DAL/APIUserData/APIUserDataAccess.php');
require_once(__DIR__.'/TelegramUserData.php');


class TelegramUserDataAccess extends CommonAccess implements APIUserDataAccess{

	public function __construct(\PDO $pdo){
		parent::__construct($pdo);

		$selectFields = "
			SELECT
				`telegramUserData`.`user_id`,
				`telegramUserData`.`telegram_id`,
				`telegramUserData`.`username`,
				`telegramUserData`.`first_name`,
				`telegramUserData`.`last_name`
		";

		$this->getAPIUserDataByTelegramIdQuery = $this->pdo->prepare("
			$selectFields
			FROM	`telegramUserData`
			WHERE	`telegramUserData`.`telegram_id` = :telegram_id
		");

		$this->getAPIUserDataByUserIdQuery = $this->pdo->prepare("
			$selectFields
			FROM	`telegramUserData`
			WHERE	`telegramUserData`.`user_id` = :user_id
		");

		$this->addAPIUserDataQuery = $this->pdo->prepare("
			INSERT INTO `telegramUserData` (
				`telegramUserData`.`user_id`,
				`telegramUserData`.`telegram_id`,
				`telegramUserData`.`username`,
				`telegramUserData`.`first_name`,
				`telegramUserData`.`last_name`
			)
			VALUES (
				:user_id,
				:telegram_id,
				:username,
				:first_name,
				:last_name
			)
		");

		$this->updateAPIUserDataQuery = $this->pdo->prepare("
			UPDATE `telegramUserData`
			SET
				`telegramUserData`.`username`	= :username,
				`telegramUserData`.`first_name`	= :first_name,
				`telegramUserData`.`last_name`	= :last_name
			WHERE
				`telegramUserData`.`telegram_id` = :telegram_id
		");
	}

	protected function buildObjectFromRow(array $row){
		$telegramUserData = new TelegramUserData(
			intval($row['user_id']),
			intval($row['telegram_id']),
			$row['username'],
			$row['first_name'],
			$row['lastname']
		);

		return $telegramUserData;
	}

	public function getAPIUserDataByTelegramId(int $telegram_id){
		$args = array(
			':telegram_id' => $telegram_id
		);

		return $this->executeSearch($this->getAPIUserDataByTelegramIdQuery, $args, QueryApproach::ONE);
	}

	public function getAPIUserDataByUserId(int $user_id){
		$args = array(
			':user_id' => $user_id
		);

		return $this->executeSearch($this->getAPIUserDataByUserIdQuery, $args, QueryApproach::ONE);
	}

	public function addAPIUserData(TelegramUserData $telegramUserData){
		$args = array(
			':user_id'		=> $telegramUserData->getUserId(),
			':telegram_id'	=> $telegramUserData->getAPISpecificId(),
			':username'		=> $telegramUserData->getUsername(),
			':first_name'	=> $telegramUserData->getFirstName(),
			':last_name'	=> $telegramUserData->getLastName()
		);

		$this->executeInsertUpdateDelete($this->addAPIUserDataQuery, $args, QueryApproach::ONE);
		return $this->getLastInsertId();
	}

	public function updateAPIUserData(TelegramUserData $telegramUserData){
		$args = array(
			':username'		=> $telegramUserData->getUsername(),
			':first_name'	=> $telegramUserData->getFirstName(),
			':last_name'	=> $telegramUserData->getLastName()
		);

		$this->executeInsertUpdateDelete($this->updateAPIUserDataQuery, $args, QueryApproach::ONE);
	}
}
