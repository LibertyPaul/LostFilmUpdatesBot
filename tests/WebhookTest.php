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

	private function send($password, $content){ // URL should contain ?password=ASDFGH12345
		$URL = WEBHOOK_URL;
		if($password !== null){
			$URL .= "?password=$password";
		}

		$resp = $this->HTTPRequester->sendJSONRequest($URL, $content);

		return $resp;
	}

	public function testPassword(){
		$resp = $this->send(null, '{}');
		$this->assertEquals(401, $resp['code']);

		$resp = $this->send('', '{}');
		$this->assertEquals(401, $resp['code']);

		$resp = $this->send('asdfgh', '{}');
		$this->assertEquals(401, $resp['code']);

		$resp = $this->send(WEBHOOK_PASSWORD, '{}');
		$this->assertEquals(500, $resp['code']);
	}

	public function testMessageLogging(){
		$key = TestsCommon\generateRandomString(32);
		$resp = $this->send(
			WEBHOOK_PASSWORD,
			"{
				\"info\": \"TEST MESSAGE\",
				\"key\": \"$key\"
			}"
		);

		$tracePath = __DIR__.'/../logs/incomingMessages.log';
		$this->assertTrue(TestsCommon\keyExists($tracePath, $key));
	}
}




