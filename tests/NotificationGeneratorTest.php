<?php
require_once(__DIR__.'/../NotificationGenerator.php');
require_once(__DIR__.'/../Message.php');
require_once(__DIR__.'/../BotPDO.php');

class NotificationGeneratorTest extends PHPUnit_Framework_TestCase{
	private $notifier;
	private $getUsersQuery;

	public function __construct(){
		$this->notifier = new NotificationGenerator();

		$pdo = BotPDO::getInstance();
		$this->getUsersQuery = $pdo->prepare('
			SELECT * FROM `users`
		');
	}

	private function asserrArrayHasKey($key, $array){
		$this->assertTrue(array_key_exists($key, $array));
	}

	public function testNewSeriesEvent(){
		$message = $this->notifier->newSeriesEvent(
			100500,
			'Test show title',
			42,
			1,
			'Test series title',
			'https://example.com'
		);
		
		$this->assertInstanceOf(Message::class, $message);
		$messageArray = $message->get();
		$this->assertTrue(is_array($messageArray));
		$this->asserrArrayHasKey('chat_id', $messageArray);
		$this->assertArrayHasKey('text', $messageArray);
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
		$this->assertInstanceOf(Message::class, $message);
		
		$messageArray = $message->get();
		$this->assertTrue(is_array($messageArray));
		$this->asserrArrayHasKey('chat_id', $messageArray);
		$this->assertArrayHasKey('text', $messageArray);
		$this->assertContains('Новый юзер', $messageArray['text']);
	}

	public function testUserLeftEvent(){
		$user_id = $this->getAnyUser()['id'];
		$message = $this->notifier->userLeftEvent($user_id);
		$this->assertInstanceOf(Message::class, $message);
		
		$messageArray = $message->get();
		$this->assertTrue(is_array($messageArray));
		$this->asserrArrayHasKey('chat_id', $messageArray);
		$this->assertArrayHasKey('text', $messageArray);
		$this->assertContains('Юзер', $messageArray['text']);
		$this->assertContains('удалился', $messageArray['text']);
	}

}



