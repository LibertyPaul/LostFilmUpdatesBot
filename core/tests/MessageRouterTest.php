<?php

require_once(__DIR__.'/../BotPDO.php');
require_once(__DIR__.'/../MessageRouter.php');
require_once(__DIR__.'/../MessageSenderInterface.php');
require_once(__DIR__.'/../../lib/HTTPRequester/FakeHTTPRequester.php');

class MessageRouterTest extends PHPUnit_Framework_TestCase{
	private function getSenders(){
		$messageSenders = array();

		$requester = new FakeHTTPRequester('/tmp/MessageRouterTest.txt');
		$telegramAPI = new \TelegramAPI\TelegramAPI('token', $requester);

		$messageSenders['TelegramAPI'] = $telegramAPI;

		return $messageSenders;
	}

	public function testRouter(){
		$messageSenders = $this->getSenders();
		$router = new \core\MessageRouter($messageSenders);
		
		$pdo = BotPDO::getInstance();
		$users = $pdo->query('
			SELECT `id` FROM `users`
		');

		$user = $users->fetch();

		$this->assertFalse($user === false);
		$result = $router->route($user['id']);

		$this->assertInstanceOf(\core\MessageSenderInterface::class, $result);
	}
}
	
