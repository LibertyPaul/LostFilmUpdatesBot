<?php

require_once(__DIR__.'/TestsCommon.php');
require_once(__DIR__.'/../config/Config.php');
require_once(__DIR__.'/../BotPDO.php');
require_once(__DIR__.'/../HTTPRequester.php');

class WebhookTest extends PHPUnit_Framework_TestCase{
	private $HTTPRequester;
	
	private $selfWebhookURL;
	private $selfWebhookPassword;


	public function __construct(){
		$config = new Config(BotPDO::getInstance());
		$this->selfWebhookURL = $config->getValue('Webhook', 'URL');
		assert($this->selfWebhookURL !== null);

		$this->selfWebhookPassword = $config->getValue('Webhook', 'Password');

		$this->HTTPRequester = new HTTPRequester();
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
		$this->assertEquals(500, $resp['code']);
	}

	public function testMessageLogging(){
		$key = TestsCommon\generateRandomString(32);
		$data = json_encode(
			array(
				'info'	=> 'TEST MESSAGE',
				'key'	=> $key
			)
		);

		$resp = $this->send($this->selfWebhookPassword, $data);

		$tracePath = __DIR__.'/../logs/incomingMessages.log';
		$this->assertTrue(TestsCommon\keyExists($tracePath, $key));

		$key = TestsCommon\generateRandomString(32);
		$this->assertFalse(TestsCommon\keyExists($tracePath, $key));
	}
}




