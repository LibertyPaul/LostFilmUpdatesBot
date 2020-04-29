<?php

namespace DAL;

require_once(__DIR__.'/../../../lib/DAL/CommonAccess.php');
require_once(__DIR__.'/../../../lib/DAL/APIUserDataInterface/APIUserDataAccess.php');
require_once(__DIR__.'/TelegramUserData.php');
require_once(__DIR__.'/TelegramUserDataBuilder.php');


class TelegramUserDataAccess extends CommonAccess implements APIUserDataAccess{

	public function __construct(\Tracer $tracer, \PDO $pdo){
		parent::__construct($tracer, $pdo, new TelegramUserDataBuilder());

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

	public function getAPIUserDataByTelegramId(int $telegram_id){
		$args = array(
			':telegram_id' => $telegram_id
		);

		return $this->executeSearch($this->getAPIUserDataByTelegramIdQuery, $args, QueryApproach::MANY);
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
			':last_name'	=> $telegramUserData->getLastName(),
			':telegram_id'	=> $telegramUserData->getAPISpecificId()
		);

		$this->executeInsertUpdateDelete($this->updateAPIUserDataQuery, $args, QueryApproach::ONE);
	}
}
