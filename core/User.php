<?php

namespace core;

require_once(__DIR__.'/BotPDO.php');

class User{
	private $id;
	private $API;
	private $isDeleted;
	private $muted;
	private $registration_time;

	private function __construct(
		$id,
		$API,
		$isDeleted,
		$muted,
		\DateTimeImmutable $registration_time
	){
		assert(is_int($id));
		assert(is_string($API));
		assert(is_bool($isDeleted));
		assert(is_bool($muted));
		
		$this->id = $id;
		$this->API = $API;
		$this->isDeleted = $isDeleted;
		$this->muted = $muted;
		$this->registration_time = $registration_time;
	}

	public static function getUser(\PDO $pdo, $user_id){
		assert(is_int($user_id));

		static $getUserQuery;
		if(isset($getUserQuery) === false){
			$getUserQuery = $pdo->prepare('
				SELECT `API`, `deleted`, `mute`, `registration_time`
				FROM `users`
				WHERE `id` = :id
			');
		}

		$getUserQuery->execute(
			array(
				'id' => $user_id
			)
		);

		$userData = $getUserQuery->fetch();
		if($userData === false){
			throw new \RuntimeException("User with id=[$user_id] was not found.");
		}

		assert($userData['deleted'] === 'Y' || $userData['deleted'] === 'N');
		$isDeleted = $userData['deleted'] === 'Y';

		assert($userData['mute'] === 'Y' || $userData['mute'] === 'N');
		$muted = $userData['mute'] === 'Y';

		$registration_time = \DateTimeImmutable::createFromFormat(
			'Y-m-d H:i:s',
			$userData['registration_time']
		);

		$user = new User(
			$user_id,
			$userData['API'],
			$isDeleted,
			$muted,
			$registration_time
		);

		return $user;
	}

	public function getId(){
		return $this->id;
	}

	public function getAPI(){
		return $this->API;
	}

	public function isDeleted(){
		return $this->isDeleted;
	}

	public function muted(){
		return $this->muted;
	}

	public function getRegistrationTime(){
		return $this->registration_time;
	}

	public function __toString(){
		$isDeletedYN = $this->isDeleted() ? 'Y' : 'N';
		$mutedYN = $this->muted() ? 'Y' : 'N';
		$regTime = $this->registration_time->format('Y-m-d H:i:s');

		$result =
			'++++++++++++[USER]+++++++++++'.PHP_EOL.
			sprintf('Id:                [%d]', $this->getId())	.PHP_EOL.
			sprintf('API:               [%s]', $this->getAPI())	.PHP_EOL.
			sprintf('Is Deleted?:       [%s]', $isDeletedYN)	.PHP_EOL.
			sprintf('Muted?:            [%s]', $mutedYN)		.PHP_EOL.
			sprintf('Registration Date: [%s]', $regTime)		.PHP_EOL.
			'+++++++++++++++++++++++++++++';

		return $result;
	}

}





