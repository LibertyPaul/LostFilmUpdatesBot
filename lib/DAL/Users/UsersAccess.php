<?php

namespace DAL;

require_once(__DIR__.'/../CommonAccess.php');
require_once(__DIR__.'/UserBuilder.php');
require_once(__DIR__.'/User.php');

class UsersAccess extends CommonAccess{
	private $getUserByIdQuery;
	private $addUserQuery;
    private $getActiveUsersQuery;
    private $getActiveUsersCountQuery;
    private $updateUserQuery;

    public function __construct(\PDO $pdo){
		parent::__construct(
			$pdo,
			new UserBuilder()
		);

		$selectFields = "
			SELECT
				`users`.`id`,
				`users`.`API`,
				`users`.`deleted`,
				`users`.`mute`,
				DATE_FORMAT(`users`.`registration_time`, '".parent::dateTimeDBFormat."') AS registrationTimeStr
		";

		$this->getUserByIdQuery = $this->pdo->prepare("
			$selectFields
			FROM	`users`
			WHERE	`users`.`id` = :id
		");

		$this->getActiveUsersQuery = $this->pdo->prepare("
			$selectFields
			FROM `users`
			WHERE `users`.`deleted` = 'N'
			AND (
				`users`.`mute` = 'N' OR
				NOT :excludeMuted
			)
			ORDER BY `users`.`id`
		");

		$this->getActiveUsersCountQuery = $this->pdo->prepare("
			SELECT COUNT(*)
			FROM `users`
			WHERE `users`.`deleted` = 'N'
			AND (
				`users`.`mute` = 'N' OR
				NOT :excludeMuted
			)
			ORDER BY `users`.`id`
		");

		$this->addUserQuery = $this->pdo->prepare("
			INSERT INTO `users` (
				`API`,
				`deleted`,
				`mute`,
				`registration_time`
			)
			VALUES (
				:API,
				:deleted,
				:mute,
				STR_TO_DATE(:registration_time, '".parent::dateTimeDBFormat."')
			)
		");

		$this->updateUserQuery = $this->pdo->prepare("
			UPDATE `users`
			SET	`users`.`deleted` = :deleted,
				`users`.`mute` = :mute
			WHERE `users`.`id` = :id
		");
	}

	public function getUserById(int $id){
		$args = array(
			':id' => $id
		);

		return $this->execute(
			$this->getUserByIdQuery,
			$args,
			\QueryTraits\Type::Read(),
			\QueryTraits\Approach::One()
		);
	}

	public function getActiveUsers(bool $excludeMuted){
		$args = array(
			':excludeMuted' => $excludeMuted
		);

		return $this->execute(
			$this->getActiveUsersQuery,
			$args,
			\QueryTraits\Type::Read(),
			\QueryTraits\Approach::Many()
		);
	}

	public function getActiveUsersCount(bool $excludeMuted){
		$args = array(
			':excludeMuted' => $excludeMuted
		);

		$this->getActiveUsersCountQuery->execute($args);

		$res = $this->getActiveUsersCountQuery->fetch();
		if($res === false){
			throw new \RuntimeException("Unable to execute [getActiveUsersCountQuery].");
		}

		return intval($res[0]);
	}

	public function addUser(User $user){
		if($user->getId() !== null){
			throw new \LogicException("Adding a user with existing id");
		}

		$args = array(
			':API'					=> $user->getAPI(),
			':deleted'				=> $user->isDeleted() ? 'Y' : 'N',
			':mute'					=> $user->isMuted() ? 'Y' : 'N',
			':registration_time'	=> $user->getRegistrationTime()->format(parent::dateTimeAppFormat)
		);

		return $this->execute(
			$this->addUserQuery,
			$args,
			\QueryTraits\Type::Write(),
			\QueryTraits\Approach::One()
		);
	}

	public function updateUser(User $user){
		if($user->getId() === null){
			throw new \LogicException("Updating user with empty id");
		}
		
		$args = array(
			':id'		=> $user->getId(),
			':deleted'	=> $user->isDeleted() ? 'Y' : 'N',
			':mute'		=> $user->isMuted() ? 'Y' : 'N'
		);

		$this->execute(
			$this->updateUserQuery,
			$args,
			\QueryTraits\Type::Write(),
			\QueryTraits\Approach::One()
		);
	}
}
