<?php

namespace TelegramAPI;

require_once(__DIR__.'/../../lib/tests/TestsCommon.php');
require_once(__DIR__.'/../../lib/Config.php');
require_once(__DIR__.'/../../core/BotPDO.php');
require_once(__DIR__.'/../../lib/HTTPRequester/HTTPRequester.php');

class WebhookTest extends \PHPUnit_Framework_TestCase{
	private $HTTPRequester;
	
	private $selfWebhookURL;
	private $selfWebhookPassword;


	public function __construct(){
		$config = \Config::getConfig(\BotPDO::getInstance());
		$this->selfWebhookURL = $config->getValue('TelegramAPI', 'Webhook URL');
		assert($this->selfWebhookURL !== null);

		$this->selfWebhookPassword = $config->getValue('TelegramAPI', 'Webhook Password');

		$this->HTTPRequester = new \HTTPRequester\HTTPRequester();
	}

	private function send($password, $content){
		$URL = $this->selfWebhookURL;
		if($password !== null){
			$URL .= "?password=$password";
		}

		$resp = $this->HTTPRequester->sendJSONRequest($URL, $content);

		return $resp;
	}

	public function testPassword(){
		$dummyMessage = json_encode(
			array(
				'some_value' => 42
			)
		);

		$resp = $this->send(null, $dummyMessage);
		$this->assertEquals(401, $resp['code']);

		$resp = $this->send('', $dummyMessage);
		$this->assertEquals(401, $resp['code']);

		$resp = $this->send('asdfgh', $dummyMessage);
		$this->assertEquals(401, $resp['code']);

		$resp = $this->send($this->selfWebhookPassword, $dummyMessage);
		$this->assertEquals(400, $resp['code']);
	}

	public function testAccountCreation(){
		$chat_id = 100500;
		$username = '🇩 🇮 🇲 🇦 🇳 ';
		$firstName = '🇩 🇮 🇲 🇦 🇳 ';
		$lastName = '🇩 🇮 🇲 🇦 🇳 ';

		
	}

	public function testMessageLogging(){
		$key = \TestsCommon\generateRandomString(32);
		$data = json_encode(
			array(
				'info'	=> 'TEST MESSAGE',
				'key'	=> $key
			)
		);

		$resp = $this->send($this->selfWebhookPassword, $data);

		$tracePath = __DIR__.'/../../logs/incomingMessages.log';
		$this->assertTrue(\TestsCommon\keyExists($tracePath, $key));

		$key = \TestsCommon\generateRandomString(32);
		$this->assertFalse(\TestsCommon\keyExists($tracePath, $key));
	}
}




