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

		$sentMessages = $this->messageTester->send('/start');

		if($isExist === false){
			$helloSent = false;
			$adminNotificationSent = false;

			foreach($sentMessages as $message){
				if(strpos($message->text, 'Привет') !== false){
					$helloSent = true;
				}

				if(strpos($message->text, 'Новый юзер') !== false){
					$adminNotificationSent = true;
				}
			}

			$this->assertTrue($helloSent);
			$this->assertTrue($adminNotificationSent);
		}
		else{
			assert(count($sentMessages) === 1);
			$resp = $sentMessages[0];
			$this->assertContains('знакомы', $resp->text);
		}
		
		$this->assertTrue($this->userExists(self::TEST_TELEGRAM_ID));
	}

	private function cancel(){
		$resp = $this->messageTester->send('/cancel');

		assert(count($resp) === 1);
		$resp = $resp[0];
		$this->assertEquals('Действие отменено.', $resp->text);
	}

	private function stop(){
		$isExist = $this->userExists(self::TEST_TELEGRAM_ID);

		$resp = $this->messageTester->send('/stop');
		assert(count($resp) === 1);
		$resp = $resp[0];

		if($isExist === false){
			$this->assertContains('Ты еще не регистрировался', $resp->text);
		}
		else{
			$this->assertContains('Ты уверен?', $resp->text);

			$sentMessages = $this->messageTester->send('Да');
			
			$bueSent = false;
			$adminNotificationSent = false;
			foreach($sentMessages as $message){
				if(strpos($message->text, 'Прощай') !== false){
					$byeSent = true;
				}

				if(strpos($message->text, 'удалился') !== false){
					$adminNotificationSent = true;
				}
			}

			$this->assertTrue($byeSent);
			$this->assertTrue($adminNotificationSent);
		}

		$this->assertFalse($this->userExists(self::TEST_TELEGRAM_ID));
	}

	public function testRegistration(){
		$this->cancel();	
		$this->start();

		$resp = $this->messageTester->send('/start')[0];
		$this->assertContains('знакомы', $resp->text);

		$resp = $this->messageTester->send('/stop')[0];
		$this->assertContains('Ты уверен?', $resp->text);
		
		$resp = $this->messageTester->send('Нет')[0];
		$this->assertContains('Фух', $resp->text);

		$this->stop();
	}

	public function testAddShow(){
		$this->cancel();
		$this->start();

		$resp = $this->messageTester->send('/add_show')[0];
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
		$resp = $this->messageTester->send($randomShow)[0];
		$this->assertEquals($randomShow.' добавлен', $resp->text);

		$this->stop();
	}
	
	public function testMute(){
		$this->cancel();
		$this->start();
		
		$pdo = createPDO();
		$getMute = $pdo->prepare('SELECT mute FROM users WHERE telegram_id = :telegram_id');
		$getMute->execute(array(':telegram_id' => self::TEST_TELEGRAM_ID));
		$res = $getMute->fetch();
		$this->assertEquals('N', $res[0]);
		
		$resp = $this->messageTester->send('/mute')[0];
		$this->assertContains('Выключил все уведомления', $resp->text);
		
		$getMute->execute(array(':telegram_id' => self::TEST_TELEGRAM_ID));
		$res = $getMute->fetch();
		$this->assertEquals('Y', $res[0]);
		
		$resp = $this->messageTester->send('/mute')[0];
		$this->assertContains('Включил все уведомления', $resp->text);
		
		$getMute->execute(array(':telegram_id' => self::TEST_TELEGRAM_ID));
		$res = $getMute->fetch();
		$this->assertEquals('N', $res[0]);
		
		$this->stop();
	}
}




