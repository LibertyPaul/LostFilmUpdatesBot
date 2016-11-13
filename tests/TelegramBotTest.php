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
		
		return intval($userExists->fetch()[0]) !== 0;
	}

	private function start(){
		$isExist = $this->userExists(self::TEST_TELEGRAM_ID);

		$resp = $this->messageTester->send('/start');

		if($isExist === false){
			$this->assertContains('Привет', $resp->text);
			$this->assertTrue($this->userExists(self::TEST_TELEGRAM_ID));
		}
		else{
			$this->assertContains('знакомы', $resp->text);
		}
	}

	private function cancel(){
		$resp = $this->messageTester->send('/cancel');
		$this->assertEquals('Действие отменено.', $resp->text);
	}

	private function stop(){
		$isExist = $this->userExists(self::TEST_TELEGRAM_ID);

		$resp = $this->messageTester->send('/stop');

		if($isExist === false){
			$this->assertContains('Ты еще не регистрировался', $resp->text);
		}
		else{
			$this->assertContains('Ты уверен?', $resp->text);

			$resp = $this->messageTester->send('Да');
			$this->assertContains('Прощай', $resp->text);
		}

		$this->assertFalse($this->userExists(self::TEST_TELEGRAM_ID));
	}

	public function testRegistration(){
		$this->cancel();	
		$this->start();

		$resp = $this->messageTester->send('/start');
		$this->assertContains('знакомы', $resp->text);

		$resp = $this->messageTester->send('/stop');
		$this->assertContains('Ты уверен?', $resp->text);
		
		$resp = $this->messageTester->send('Нет');
		$this->assertContains('Фух', $resp->text);

		$this->stop();
	}

	public function testAddShow(){
		$this->cancel();
		$this->start();

		$resp = $this->messageTester->send('/add_show');
		$this->assertContains('Как называется сериал?', $resp->text);

		$keyboard = $resp->reply_markup->keyboard;
		$this->assertTrue(isset($keyboard));
		$this->assertTrue(isset($keyboard[0]));
		$this->assertEquals('/cancel', $keyboard[0][0]);


		$keyboardRows = count($keyboard);
		$this->assertGreaterThan(0, $keyboardRows);

		$showCount = count($keyboard[0]) - 1;
		if($keyboardRows > 1){
			$showCount += ($keyboardRows - 2) * 2 + count($keyboard[$keyboardRows - 1]);
		}

		$randomIndex = rand(0, $showCount);

		$row = ($randomIndex - 1) / 2;
		$col = ($randomIndex - 1) % 2;

		$randomShow = $keyboard[$row][$col];
		$resp = $this->messageTester->send($randomShow);
		$this->assertEquals($randomShow.' добавлен', $resp->text);

		$this->stop();
	}

}
