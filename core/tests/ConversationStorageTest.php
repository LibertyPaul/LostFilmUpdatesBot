<?php

namespace core;

require_once(__DIR__.'/../ConversationStorage.php');

class ConversationStorageTest extends \PHPUnit_Framework_TestCase{

	public function test(){
		$user_id = rand(0, 999999999999999);
		
		$storage = new ConversationStorage($user_id);
		$this->assertEquals(array(), $storage->getConversation());

		$testMessage1 = '
			─────────▄──────────────▄────
			────────▌▒█───────────▄▀▒▌───
			────────▌▒▒▀▄───────▄▀▒▒▒▐───
			───────▐▄▀▒▒▀▀▀▀▄▄▄▀▒▒▒▒▒▐───
			─────▄▄▀▒▒▒▒▒▒▒▒▒▒▒█▒▒▄█▒▐───
			───▄▀▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▀██▀▒▌───
			──▐▒▒▒▄▄▄▒▒▒▒▒▒▒▒▒▒▒▒▒▀▄▒▒▌──
			──▌▒▒▐▄█▀▒▒▒▒▄▀█▄▒▒▒▒▒▒▒█▒▐──
			─▐▒▒▒▒▒▒▒▒▒▒▒▌██▀▒▒▒▒▒▒▒▒▀▄▌─
			─▌▒▀▄██▄▒▒▒▒▒▒▒▒▒▒▒░░░░▒▒▒▒▌─
			─▌▀▐▄█▄█▌▄▒▀▒▒▒▒▒▒░░░░░░▒▒▒▐─
			▐▒▀▐▀▐▀▒▒▄▄▒▄▒▒▒▒▒░░░░░░▒▒▒▒▌
			▐▒▒▒▀▀▄▄▒▒▒▄▒▒▒▒▒▒░░░░░░▒▒▒▐─
			─▌▒▒▒▒▒▒▀▀▀▒▒▒▒▒▒▒▒░░░░▒▒▒▒▌─
			─▐▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▐──
			──▀▄▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▄▒▒▒▒▌──
			────▀▄▒▒▒▒▒▒▒▒▒▒▄▄▄▀▒▒▒▒▄▀───
			───▐▀▒▀▄▄▄▄▄▄▀▀▀▒▒▒▒▒▄▄▀─────
			──▐▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▒▀▀────────
		';
		$storage->insertMessage($testMessage1);

		$testMessage2 = '/WOW - SO UNIT - SO TESTY/';
		$storage->insertMessage($testMessage2);

		$testMessage3 = '/BA DUM TSS/';
		$storage->insertMessage($testMessage3);

		$this->assertEquals(
			array($testMessage1, $testMessage2, $testMessage3),
			$storage->getConversation()
		);

		$this->assertEquals($testMessage1, $storage->getFirstMessage());
		$this->assertEquals($testMessage3, $storage->getLastMessage());

		$this->assertEquals(3, $storage->getConversationSize());

		$storage->deleteLastMessage();

		$testMessage4 = '/TSS DUM BA/';
		$storage->insertMessage($testMessage4);

		$this->assertEquals(
			array($testMessage1, $testMessage2, $testMessage4),
			$storage->getConversation()
		);

		
		$storage = new ConversationStorage($user_id);


		$this->assertEquals(
			array($testMessage1, $testMessage2, $testMessage4),
			$storage->getConversation()
		);

		$storage->deleteConversation();
		$this->assertEquals(array(), $storage->getConversation());


		$storage = new ConversationStorage($user_id);
		$this->assertEquals(array(), $storage->getConversation());
	}
}


