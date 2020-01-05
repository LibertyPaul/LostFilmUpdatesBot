<?php

namespace DAL;

require_once(__DIR__.'/../CommonAccess.php');
require_once(__DIR__.'/User.php');


class UsersAccess extends CommonAccess{
	private $pdo;

	private $getUserByIdQuery;
	private $addUserQuery;

	public function __construct(\PDO $pdo){
		$this->pdo = $pdo;

		$selectFields = "
			SELECT
				`users`.`id`,
				`users`.`API`,
				`users`.`deleted`,
				`users`.`mute`,
				`users`.`registration_time`
		";

		$this->getUserByIdQuery = $this->pdo->prepare("
			$selectFields
			FROM `users`
			WHERE `users`.`id` = :id
		");

		$this->getActiveUsers = $this->pdo->prepare("
			$selectFields
			FROM `users`
			WHERE `users`.`deleted` = 'N'
			AND (
				`users`.`mute` = 'N' OR
				NOT :excludeMuted
			)
			ORDER BY `users`.`id`
		");

		$this->addUserQuery = $this->pdo->prepare("
			INSERT INTO `users` (`API`, `deleted`, `mute`, `registration_time`)
			VALUES (:API, :deleted, :mute, :registration_time)
		");

		$this->updateUserQuery = $this->pdo->prepare("
			UPDATE `users`
			SET	`users`.`deleted` = :deleted,
				`users`.`mute` = :mute
			WHERE `users`.`id` = :id
		");
	}

	protected function buildObjectFromRow(array $row){
		$user = new User(
			intval($row['id']),
			$row['API'],
			$row['deleted'] === 'Y',
			$row['mute'] === 'Y',
			\DateTimeImmutable::createFromFormat(parent::dateTimeAppFormat, $row['registration_time'])
		);

		return $user;
	}

	public function getUserById(int $id){
		$args = array(
			':id' => $id
		);

		return $this->executeSearch($this->getUserByIdQuery, $args, QueryApproach::ONE);
	}

	public function getActiveUsers(bool $excludeMuted){
		$args = array(
			':excludeMuted' => $excludeMuted
		);

		return $this->executeSearch($this->getActiveUsersQuery, $args, QueryApproach::MANY);
	}

	public function addUser(User $user){
		if($user->getId() !== null){
			throw new \RuntimeError("Adding a user with existing id");
		}

		$args = array(
			':API'					=> $user->getAPI(),
			':deleted'				=> $user->isDeleted() ? 'Y' : 'N',
			':mute'					=> $user->isMuted() ? 'Y' : 'N',
			':registration_time'	=> $user->getRegistrationTime()->format(parent::dateTimeDBFormat)
		);

		$this->executeInsertUpdateDelete($this->addUserQuery, $args, QueryApproach::ONE);
		return intval($this->pdo->lastInsertId());
	}

	public function updateUser($user){
		if($user->getId() === null){
			throw new \RuntimeException("Updating user with empty id");
		}
		
		$args = array(
			':deleted'	=> $user->isDeleted() ? 'Y' : 'N',
			':mute'		=> $user->isMuted() ? 'Y' : 'N'
		);

		$this->executeInsertUpdateDelete($this->updateUserQuery, $args, QueryApproach::ONE);
	}
}
