<?php

namespace core;

require_once(__DIR__.'/../NotificationGenerator.php');
require_once(__DIR__.'/../OutgoingMessage.php');
require_once(__DIR__.'/../BotPDO.php');

class NotificationGeneratorTest extends \PHPUnit_Framework_TestCase{
	private $notifier;
	private $getUsersQuery;

	public function __construct(){
		$this->notifier = new NotificationGenerator();

		$pdo = \BotPDO::getInstance();
		$this->getUsersQuery = $pdo->prepare('
			SELECT * FROM `users`
		');
	}

	private function asserrArrayHasKey($key, $array){
		$this->assertTrue(array_key_exists($key, $array));
	}

	public function testNewSeriesEvent(){
		$message = $this->notifier->newSeriesEvent(
			'Test show title',
			42,
			1,
			'Test series title',
			'https://example.com'
		);
		
		$this->assertInstanceOf(OutgoingMessage::class, $message);
	}

	private function getAnyUser(){
		$this->getUsersQuery->execute();
		$anyUser = $this->getUsersQuery->fetch();
		if($anyUser === false){
			throw new OutOfBoundsException('No users were found');
		}
		return $anyUser;
	}

	public function testNewUserEvent(){
		$user_id = $this->getAnyUser()['id'];

		$message = $this->notifier->newUserEvent($user_id);
		$this->assertInstanceOf(DirectedOutgoingMessage::class, $message);
	}

	public function testUserLeftEvent(){
		$user_id = $this->getAnyUser()['id'];
		$message = $this->notifier->userLeftEvent($user_id);
		$this->assertInstanceOf(DirectedOutgoingMessage::class, $message);
	}

}



