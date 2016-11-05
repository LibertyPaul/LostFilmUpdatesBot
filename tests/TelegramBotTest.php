<?php

require_once(__DIR__.'/MessageTester.php');
require_once(__DIR__.'/../config/stuff.php');

class TelegramBotTest extends PHPUnit_Framework_TestCase{
	const TEST_TELEGRAM_ID = 100500;
	private $messageTester;

	public function __construct(){
		$this->messageTester = new MessageTester(self::TEST_TELEGRAM_ID);
	}

	private function userExists($telegram_id){
		$pdo = createPDO();
		$userExists = $pdo->prepare('
			SELECT COUNT(*) FROM `users` WHERE `telegram_id` = :telegram_id
		');

		$userExists->execute(
			array(
				':telegram_id' => $telegram_id
			)
		);
		
		return intval($userExists->fetch()[0]) === 0;
	}
	
	public function testRegistration(){
		
		if($this->userExists(self::TEST_TELEGRAM_ID) === false){

			$resp = $this->messageTester->send('/start');
			$this->assertContains('Привет', $resp);

			$this->assertTure($this->userExists(self::TEST_TELEGRAM_ID));

		}

		$resp = $this->messageTester->send('/start');
		$this->assertContains('знакомы', $resp);

		$resp = $this->messageTester->send('/stop');
		$this->assertContains('Ты уверен?', $resp);
		
		$resp = $this->messageTester->send('Нет');
		$this->assertContains('Фух', $resp);

		$resp = $this->messageTester->send('/stop');
		$this->assertContains('Ты уверен?', $resp);

		$resp = $this->messageTester->send('Да');
		$this->assertContains('Прощай', $resp);


		$this->assertFalse($this->userExists(self::TEST_TELEGRAM_ID));

	}

	public function testAddShow(){
		
		if($this->userExists(self::TEST_TELEGRAM_ID) === false){

			$resp = $this->messageTester->send('/start');
			$this->assertContains('Привет', $resp);

			$this->assertTure($this->userExists(self::TEST_TELEGRAM_ID));

		}


		$resp = $this->messageTester->send('/add_show');
		$this->assertContains('Как называется сериал?', $resp);

		$showList = json_decode($resp);
		$this->assertEquals('/cancel', $showList->keyboard[0][0]);


		$randomShow = $showList->keyboard[rand(1, count($showList->keyboard) - 1)][rand(0, 1)];
		$resp = $this->messageTester->send($randomShow);
		$this->assertEquals($randomShow.' добавлен', $resp);


	}
}
		



















