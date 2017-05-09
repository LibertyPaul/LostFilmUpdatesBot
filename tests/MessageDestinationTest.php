<?php

require_once(__DIR__.'/../core/MessageDestination.php');

class MessageDestinationTest extends PHPUnit_Framework_TestCase{

	public function testInvalidAPI(){
		$this->expectException(InvalidArgumentException::class);
		$md = new core\MessageDestination(-1, null);
	}

	# Telegram API

	public function testTelegramAPIValid(){
		$ids = array(0, 42, 999999999999999);
		foreach($ids as $id){
			$md = new core\MessageDestination(core\DestinationTypes::TelegramAPI, $id);
			
			$this->assertEquals(core\DestinationTypes::TelegramAPI, $md->getType());
			$this->assertEquals($id, $md->getIdentifier());
		}
	}

	public function testTelegramAPINegaitve(){
		$this->expectException(InvalidArgumentException::class);
		$md = new core\MessageDestination(core\DestinationTypes::TelegramAPI, -1);
	}

	public function testTelegramAPIWrongType(){
		$this->expectException(InvalidArgumentException::class);
		$md = new core\MessageDestination(core\DestinationTypes::TelegramAPI, 'asdfgh');
	}

	public function testTelegramAPINullIdentifier(){
		$this->expectException(InvalidArgumentException::class);
		$md = new core\MessageDestination(core\DestinationTypes::TelegramAPI, null);
	}

	# VK API (the same as TelegramAPI, but who cares about tests, eh?)

	public function testVKAPIValid(){
		$ids = array(0, 42, 999999999999999);
		foreach($ids as $id){
			$md = new core\MessageDestination(core\DestinationTypes::VKAPI, $id);
			
			$this->assertEquals(core\DestinationTypes::VKAPI, $md->getType());
			$this->assertEquals($id, $md->getIdentifier());
		}
	}

	public function testVKAPINegaitve(){
		$this->expectException(InvalidArgumentException::class);
		$md = new core\MessageDestination(core\DestinationTypes::VKAPI, -1);
	}

	public function testVKAPIWrongType(){
		$this->expectException(InvalidArgumentException::class);
		$md = new core\MessageDestination(core\DestinationTypes::VKAPI, 'asdfgh');
	}

	public function testVKAPINullIdentifier(){
		$this->expectException(InvalidArgumentException::class);
		$md = new core\MessageDestination(core\DestinationTypes::VKAPI, null);
	}

}






