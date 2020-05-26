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
				`telegramUserData`.`chat_id`,
				`telegramUserData`.`type`,
				`telegramUserData`.`username`,
				`telegramUserData`.`first_name`,
				`telegramUserData`.`last_name`
		";

		$this->getAPIUserDataByChatIDQuery = $this->pdo->prepare("
			$selectFields
			FROM	`telegramUserData`
			WHERE	`telegramUserData`.`chat_id` = :chat_id
		");

		$this->getAPIUserDataByUserIdQuery = $this->pdo->prepare("
			$selectFields
			FROM	`telegramUserData`
			WHERE	`telegramUserData`.`user_id` = :user_id
		");

		$this->addAPIUserDataQuery = $this->pdo->prepare("
			INSERT INTO `telegramUserData` (
				`telegramUserData`.`user_id`,
				`telegramUserData`.`chat_id`,
				`telegramUserData`.`type`,
				`telegramUserData`.`username`,
				`telegramUserData`.`first_name`,
				`telegramUserData`.`last_name`
			)
			VALUES (
				:user_id,
				:chat_id,
				:type,
				:username,
				:first_name,
				:last_name
			)
		");

		$this->updateAPIUserDataQuery = $this->pdo->prepare("
			UPDATE `telegramUserData`
			SET
				`chat_id`		= :chat_id,
				`type`			= :type,
				`username`		= :username,
				`first_name`	= :first_name,
				`last_name`		= :last_name
			WHERE
				`user_id`		= :user_id
		");
	}

	public function getAPIUserDataByChatID(int $chat_id){
		$args = array(
			':chat_id' => $chat_id
		);

		return $this->execute(
			$this->getAPIUserDataByChatIDQuery,
			$args,
			\QueryTraits\Type::Read(),
			\QueryTraits\Approach::Many()
		);
	}

	public function getAPIUserDataByUserId(int $user_id){
		$args = array(
			':user_id' => $user_id
		);

		return $this->execute(
			$this->getAPIUserDataByUserIdQuery,
			$args,
			\QueryTraits\Type::Read(),
			\QueryTraits\Approach::One()
		);
	}

	public function addAPIUserData(TelegramUserData $telegramUserData){
		$args = array(
			':user_id'		=> $telegramUserData->getUserId(),
			':chat_id'		=> $telegramUserData->getAPISpecificId(),
			':type'			=> $telegramUserData->getType(),
			':username'		=> $telegramUserData->getUsername(),
			':first_name'	=> $telegramUserData->getFirstName(),
			':last_name'	=> $telegramUserData->getLastName()
		);

		$this->execute(
			$this->addAPIUserDataQuery,
			$args,
			\QueryTraits\Type::Write(),
			\QueryTraits\Approach::One()
		);

		return $this->getLastInsertId();
	}

	public function updateAPIUserData(TelegramUserData $telegramUserData){
		$args = array(
			':user_id'		=> $telegramUserData->getUserId(),
			':chat_id'		=> $telegramUserData->getAPISpecificId(),
			':type'			=> $telegramUserData->getType(),
			':username'		=> $telegramUserData->getUsername(),
			':first_name'	=> $telegramUserData->getFirstName(),
			':last_name'	=> $telegramUserData->getLastName()
		);

		$this->execute(
			$this->updateAPIUserDataQuery,
			$args,
			\QueryTraits\Type::Write(),
			\QueryTraits\Approach::One()
		);
	}
}
