<?php

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
		$this->assertEquals($resp['code'], 401);

		$resp = $this->send('', '{}');
		$this->assertEquals($resp['code'], 401);

		$resp = $this->send('asdfgh', '{}');
		$this->assertEquals($resp['code'], 401);

		$resp = $this->send(WEBHOOK_PASSWORD, '{}');
		$this->assertEquals($resp['code'], 200);
	}

	public function testMessageLogging(){
		
		
	}
}




