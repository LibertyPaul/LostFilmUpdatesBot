<?php

require_once(__DIR__.'/TestsCommon.php');
require_once(__DIR__.'/../config/config.php');
require_once(__DIR__.'/../HTTPRequester.php');

class WebhookTest extends PHPUnit_Framework_TestCase{
	private $HTTPRequester;

	public function __construct(){
		if(defined('WEBHOOK_URL') === false){
			throw new Exception('WEBHOOK_URL is not defined');
		}

		$this->HTTPRequester = new HTTPRequester();

	}

	private function send($password, $content){
		$URL = WEBHOOK_URL;
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

		$resp = $this->send(WEBHOOK_PASSWORD, $dummyMessage);
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

		$resp = $this->send(WEBHOOK_PASSWORD, $data);

		$tracePath = __DIR__.'/../logs/incomingMessages.log';
		$this->assertTrue(TestsCommon\keyExists($tracePath, $key));

		$key = TestsCommon\generateRandomString(32);
		$this->assertFalse(TestsCommon\keyExists($tracePath, $key));
	}
}




