<?php

namespace CommandSubstitutor;

require_once(__DIR__.'/../../../core/BotPDO.php');
require_once(__DIR__.'/../CommandSubstitutor.php');

class CommandSubstitutorTest extends \PHPUnit_Framework_TestCase{
	public function test(){
		$pdo = \BotPDO::getInstance();	
		$substitutor = new CommandSubstitutor($pdo);

		$this->assertEquals(
			'CoreCommand::Start',
			$substitutor->convertAPIToCore('TelegramAPI', '/start')->getText()
		);

		$APICommands = $substitutor->convertCoreToAPI(
			'TelegramAPI',
			'CoreCommand::Start'
		);

		$APICommandsText = array();
		foreach($APICommands as $APICommand){
			$APICommandsText[] = $APICommand->getText();
		}

		$this->assertContains('/start',	$APICommandsText);


		$coreCommand1 = $substitutor->getCoreCommand(CoreCommandMap::Start);
		$this->assertTrue($coreCommand1 !== null);

		$coreCommand2 = $substitutor->getCoreCommand(CoreCommandMap::AddShow);
		$this->assertTrue($coreCommand2 !== null);

		$coreCommand100500 = $substitutor->getCoreCommand(100500);
		$this->assertTrue($coreCommand100500 === null);

		$text = 'Hi! Following text will be replaced: CoreCommand::Help.';
		$API = 'TelegramAPI';
		$replaced = $substitutor->replaceCoreCommandsInText($API, $text);
		$this->assertContains('/help', $replaced);
		$this->assertContains('Помощь', $replaced);
		$this->assertContains('Инфо', $replaced);
		$this->assertContains('Команды', $replaced);

		$allCoreCmnds = $substitutor->getCoreCommandsAssociative();
		$this->assertContains('CoreCommand::Start', $allCoreCmnds);
		$this->assertContains('CoreCommand::Help', $allCoreCmnds);
		$this->assertContains('CoreCommand::AddShow', $allCoreCmnds);
		$this->assertContains('CoreCommand::RemoveShow', $allCoreCmnds);
		$this->assertContains('CoreCommand::Mute', $allCoreCmnds);
		$this->assertContains('CoreCommand::Stop', $allCoreCmnds);
	}
}
					











