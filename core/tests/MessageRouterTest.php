<?php

require_once(__DIR__.'/../BotPDO.php');
require_once(__DIR__.'/../MessageRouter.php');
require_once(__DIR__.'/../MessageSenderInterface.php');
require_once(__DIR__.'/../../lib/HTTPRequester/FakeHTTPRequester.php');
require_once(__DIR__.'/../../TelegramAPI/MessageSender.php');

class MessageRouterTest extends PHPUnit_Framework_TestCase{
	private function getSenders(){
		$messageSenders = array();

		$requester = new FakeHTTPRequester('/tmp/MessageRouterTest.txt');
		$telegramAPI = new \TelegramAPI\TelegramAPI('token', $requester);
		$telegramAPISender = new \TelegramAPI\MessageSender($telegramAPI);

		$messageSenders['TelegramAPI'] = $telegramAPISender;

		return $messageSenders;
	}

	public function testRouter(){
		$messageSenders = $this->getSenders();
		$router = new \core\MessageRouter($messageSenders);
		
		$pdo = BotPDO::getInstance();
		$user = $pdo->query("SELECT `id` FROM `users` WHERE `deleted` = 'N'")->fetch();
		$this->assertFalse($user === false);

		$result = $router->route($user['id']);

		$this->assertInstanceOf(\core\MessageRoute::class, $result);
	}
}
	
