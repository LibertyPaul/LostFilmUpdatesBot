<?php

require_once(__DIR__.'/TelegramBotMockFactory.php');
require_once(__DIR__.'/../config/stuff.php');

class TelegramBotTest extends PHPUnit_Framework_TestCase{
	const messageTemplate = '{
		"update_id": 0,
		"message": {
			"message_id": 0,
			"from": {
			    "id": #TELEGRAM_ID,
			    "first_name": "#FIRST_NAME",
			    "username": "#USERNAME"
			},
			"chat": {
			    "id": #CHAT_ID,
			    "first_name": "#FIRST_NAME",
			    "username": "#USERNAME",
			    "type": "private"
			},
			"date": 0,
			"text": "#TEXT"
		}
	}';
	
	const filedsToInsert = array(
		array('key' => '#TEXT', 		'defaultValue' => null),
		array('key' => '#FIRST_NAME', 	'defaultValue' => 'test first name'),
		array('key' => '#USERNAME', 	'defaultValue' => 'test username'),
		array('key' => '#TELEGRAM_ID', 	'defaultValue' => null),
		array('key' => '#CHAT_ID', 		'defaultValue' => null)
	);
	
	private function fillTemplate(array $fields){
		$keys = array();
		$values = array();
		
		foreach(self::filedsToInsert as $fieldToInsert){
			$key = $fieldToInsert['key'];
			$keys[] = $key;
			$value = null;
			
			if(isset($fields[$key])){
				$value = $fields[$key];
			}
			else{
				if($fieldToInsert['defaultValue'] === null){
					throw new Exception($fieldToInsert['key'].' is required');
				}
				
				$value = $fieldToInsert['defaultValue'];
			}
			
			$values[] = $value;
		}
		
		return str_replace($keys, $values, self::messageTemplate);
	}
	
	private function getMethod($name){
		$rc = new ReflectionClass('TelegramBot');
		$method = $rc->getMethod($name);
		$method->setAccessible(true);
		return $method;
	}
	
	public function testRegistration(){
		$tmpFilePath = tempnam('/tmp', 'TelegramBotTest::registrationTest');
		
		$botFactory = new TelegramBotMockFactory($tmpFilePath);
		
		$telegram_id = 100500;
		$chat_id = 100500;
		
		$bot = $botFactory->createBot($telegram_id, $chat_id);
		$getUserIdMethod = $this->getMethod('getUserId');
		
		$thrown = false;
		
		try{
			$getUserIdMethod->invoke($bot, $telegram_id);
		}
		catch(TelegramException $tex){
			$thrown = true;
		}
		
		$this->assertTrue($thrown);
		
		
		$msg = $this->fillTemplate(
			array(
				'#TEXT' 		=> '/start',
				'#FIRST_NAME'	=> 'firstName',
				'#USERNAME'		=> 'username',
				'#TELEGRAM_ID'	=> $telegram_id,
				'#CHAT_ID'		=> $chat_id
			)
		);
		
		$bot->incomingUpdate(json_decode($msg)->message);
		
		$this->assertThat(
			$getUserIdMethod->invoke($bot, $telegram_id),
			$this->logicalNot(
				$this->equalTo(null)
			)
		);
		
		
		$msg = $this->fillTemplate(
			array(
				'#TEXT' 		=> '/stop',
				'#FIRST_NAME'	=> 'firstName',
				'#USERNAME'		=> 'username',
				'#TELEGRAM_ID'	=> $telegram_id,
				'#CHAT_ID'		=> $chat_id
			)
		);
		
		$bot->incomingUpdate(json_decode($msg)->message);
		
		$msg = $this->fillTemplate(
			array(
				'#TEXT' 		=> 'Да',
				'#FIRST_NAME'	=> 'firstName',
				'#USERNAME'		=> 'username',
				'#TELEGRAM_ID'	=> $telegram_id,
				'#CHAT_ID'		=> $chat_id
			)
		);
		
		$bot->incomingUpdate(json_decode($msg)->message);
		
		try{
			$getUserIdMethod->invoke($bot, $telegram_id);
		}
		catch(TelegramException $tex){
			$thrown = true;
		}
		
		$this->assertTrue($thrown);
	}
}
		



















