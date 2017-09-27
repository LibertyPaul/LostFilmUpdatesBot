<?php

namespace core;

require_once(__DIR__.'/../User.php');
require_once(__DIR__.'/../BotPDO.php');

class UserTest extends \PHPUnit_Framework_TestCase{
	public function testFields(){
		$pdo = \BotPDO::getInstance();
		$stmt = $pdo->query('SELECT * FROM `users`');
		
		$count = 5;

		$userData = $stmt->fetch();

		while($userData !== false && $count --> 0){
			
			$user_id = intval($userData['id']);

			$user = User::getUser($pdo, $user_id);

			$this->assertEquals($user_id, $user->getId());
			$this->assertEquals($userData['API'], $user->getAPI());
			$this->assertEquals($userData['deleted'] === 'Y', $user->isDeleted());
			$this->assertEquals($userData['mute'] === 'Y', $user->muted());
			$this->assertEquals(
				\DateTimeImmutable::createFromFormat(
					'Y-m-d H:i:s',
					$userData['registration_time']
				),
				$user->getRegistrationTime()
			);

			$userData = $stmt->fetch();
		}

	}

	public function testToString(){
		$pdo = \BotPDO::getInstance();
		$stmt = $pdo->query('SELECT * FROM `users`');
		
		$count = 5;

		$userData = $stmt->fetch();

		while($userData !== false && $count --> 0){
			$user_id = intval($userData['id']);
			$user = User::getUser($pdo, $user_id);
			$this->assertContains('+++[USER]+++', $user->__toString());
			$userData = $stmt->fetch();
		}
	
	}
}


