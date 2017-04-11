<?php
require_once(__DIR__.'/../BotPDO.php');
require_once(__DIR__.'/MessageTester.php');

class UserControllerTest extends PHPUnit_Framework_TestCase{
	const TEST_TELEGRAM_ID = 100500;
	private $userController;

	public function __construct(){
		$this->messageTester = new MessageTester(
			self::TEST_TELEGRAM_ID,
			'🇩 🇮 🇲  🇦  🇳 ',
			'🇩 🇮 🇲  🇦  🇳 ', // once such shitty nickname was unable to register
			'🇩 🇮 🇲  🇦  🇳 '
		);

	}

	private function userExists($telegram_id){
		$pdo = BotPDO::getInstance();
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

		$response = $this->messageTester->send('/start');

		if($isExist === false){
			$helloSent = false;
			$adminNotificationSent = false;

			foreach($response as $message){
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
			assert(count($response) === 1);
			$resp = $response[0];
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

	private static function randomShowFromKeyboard($keyboard){
		$showList = array();
		foreach($keyboard as $row){
			foreach($row as $showTitle){
				if($showTitle !== '/cancel'){
					$showList[] = $showTitle;
				}
			}
		}

		if(empty($showList)){
			throw new RuntimeException('The keyboard is empty');
		}

		$showCount = count($showList);
		$i = rand(0, $showCount - 1);
		return $showList[$i];
	}

	public function testAddShow(){
		$this->cancel();
		$this->start();

		$resp = $this->messageTester->send('/add_show')[0];
		$this->assertContains('Как называется сериал?', $resp->text);

		$keyboard = $resp->reply_markup->keyboard;
		$this->assertTrue(isset($keyboard));
		$this->assertNotEmpty($keyboard);
		$this->assertNotEmpty($keyboard[0]);
		$this->assertEquals('/cancel', $keyboard[0][0]);


		$randomShow = self::randomShowFromKeyboard($keyboard);
		$resp = $this->messageTester->send($randomShow)[0];
		$this->assertEquals($randomShow.' добавлен', $resp->text);

		$this->stop();
	}

	public function testAddShowComplex(){
		$this->cancel();
		$this->start();

		$resp = $this->messageTester->send('/add_show')[0];
		$this->assertContains('Как называется сериал?', $resp->text);

		$resp = $this->messageTester->send('Американская')[0];
		$keyboard = $resp->reply_markup->keyboard;
		$this->assertTrue(isset($keyboard));
		$this->assertNotEmpty($keyboard);
		$this->assertNotEmpty($keyboard[0]);
		$this->assertEquals('/cancel', $keyboard[0][0]);


		$randomShow = self::randomShowFromKeyboard($keyboard);
		$resp = $this->messageTester->send($randomShow)[0];
		$this->assertContains('добавлен', $resp->text);
		
		$this->stop();
	}

	public function testMute(){
		$this->cancel();
		$this->start();
		
		$pdo = BotPDO::getInstance();
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




