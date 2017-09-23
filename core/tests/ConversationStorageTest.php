<?php

namespace core;

require_once(__DIR__.'/../ConversationStorage.php');
require_once(__DIR__.'/../IncomingMessage.php');

class ConversationStorageTest extends \PHPUnit_Framework_TestCase{

	public function test(){
		$user_id = rand(0, 999999999999999);
		
		$storage = new ConversationStorage($user_id);
		$this->assertEquals(array(), $storage->getConversation());

		$text1 = '
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
		$text2 = '/WOW - SO UNIT - SO TESTY/';
		$text3 = '/BA DUM TSS/';
		$text4 = '/TSS DUM BA/';

		$command1 = new UserCommand(UserCommandMap::Donate);
		$command2 = new UserCommand(UserCommandMap::GetMyShows);
		$command3 = new UserCommand(UserCommandMap::GetShareButton);
		$command4 = new UserCommand(UserCommandMap::Broadcast);

		$testMessage1 = new IncomingMessage($user_id, $command1, $text1, 10001);
		$testMessage2 = new IncomingMessage($user_id, $command2, $text2, 10002);
		$testMessage3 = new IncomingMessage($user_id, $command3, $text3, 10003);
		$testMessage4 = new IncomingMessage($user_id, $command4, $text4, 10004);


		$storage->insertMessage($testMessage1);
		$storage->insertMessage($testMessage2);
		$storage->insertMessage($testMessage3);

		$this->assertEquals(
			array($testMessage1, $testMessage2, $testMessage3),
			$storage->getConversation()
		);

		$this->assertEquals($text1, $storage->getFirstMessage()->getText());
		$this->assertEquals($text2, $storage->getMessage(1)->getText());
		$this->assertEquals($text3, $storage->getLastMessage()->getText());

		$this->assertEquals(3, $storage->getConversationSize());

		$storage->deleteLastMessage();

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


