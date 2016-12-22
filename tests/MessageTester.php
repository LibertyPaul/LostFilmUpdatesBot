<?php

require_once(__DIR__.'/TelegramBotMockFactory.php');
require_once(__DIR__.'/../Exceptions/TelegramException.php');

class MessageTester{

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
			    "id": #TELEGRAM_ID,
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
		array('key' => '#USERNAME', 	'defaultValue' => 'test username')
	);
	
	private $telegram_id;
	private $botFactory;
	private $botOutputFile;

	public function __construct($telegram_id){
		assert(is_int($telegram_id));
		$this->telegram_id = $telegram_id;
		
		$this->botOutputFile = tempnam(sys_get_temp_dir(), 'MessageTester_');

		$this->botFactory = new TelegramBotMockFactory($this->botOutputFile);
	}
	
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

		$keys[] = '#TELEGRAM_ID';
		$values[] = $this->telegram_id;
		
		return str_replace($keys, $values, self::messageTemplate);
	}

	private function truncateBotOutputFile(){
		$hFile = fopen($this->botOutputFile, 'r+');
		assert($hFile !== false);

		assert(ftruncate($hFile, 0));
		assert(fclose($hFile));
	}

	public function send($message){
		$json_msg = $this->fillTemplate(array('#TEXT' => $message));

		$this->truncateBotOutputFile();

		$bot = $this->botFactory->createBot($this->telegram_id);
		
		try{
			$msg = json_decode($json_msg);
			assert(json_last_error() === JSON_ERROR_NONE);

			$bot->incomingUpdate($msg->message);
		}
		catch(TelegramException $tex){
			$tex->release();
		}

		$response_json = file_get_contents($this->botOutputFile);
		assert($response_json !== false);

		$response_json = trim($response_json);
		$sentMessages_json = explode("\n\n", $response_json);

		$sentMessages = array();
		foreach($sentMessages_json as $message_json){
			$currentMessage = json_decode(trim($message_json));
			assert(json_last_error() === JSON_ERROR_NONE);

			$sentMessages[] = $currentMessage;
		}
		
		return $sentMessages;
	}


}



















