<?php
require_once(__DIR__.'/../FakeHTTPRequester.php');
require_once(__DIR__.'/../TelegramAPI.php');
require_once(__DIR__.'/../UpdateHandler.php');
require_once(__DIR__.'/../BotPDO.php');

class MessageTester{

	const messageTemplate = '{
		"update_id": #UPDATE_ID,
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
		array('key' => '#UPDATE_ID',	'defaultValue' => 'null'),
		array('key' => '#TEXT', 		'defaultValue' => null),
		array('key' => '#FIRST_NAME', 	'defaultValue' => 'test first name'),
		array('key' => '#USERNAME', 	'defaultValue' => 'test username')
	);

	private $username;
	private $firstName;
	private $lastName;
	
	private $telegram_id;
	private $botOutputFile;
	private $updateHandler;

	public function __construct(
		$telegram_id,
		$username = 'test username',
		$firstName = 'test first name',
		$lastName = 'test last name'
	){
		assert(is_int($telegram_id));
		$this->telegram_id = $telegram_id;
		$this->username = $username;
		$this->firstName = $firstName;
		$this->lastName = $lastName;
		
		$this->botOutputFile = tempnam(sys_get_temp_dir(), 'MessageTester_');

		$HTTPRequester = new FakeHTTPRequester($this->botOutputFile);
		
		$config = new Config(BotPDO::getInstance());
		$botToken = $config->getValue('TelegramAPI', 'token');
		assert($botToken !== null);

		$telegramAPI = new TelegramAPI($botToken, $HTTPRequester);
		$this->updateHandler = new UpdateHandler($telegramAPI);
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

	public function send($text, $update_id = null){
		$json_update = $this->fillTemplate(
			array(
				'#UPDATE_ID'	=> $update_id === null ? $update_id : 'null',
				'#TEXT'			=> $text,
				'#USERNAME'		=> $this->username,
				'#FIRST_NAME'	=> $this->lastName
			)
		);

		$this->truncateBotOutputFile();

		$update = json_decode($json_update);
		assert(json_last_error() === JSON_ERROR_NONE);
		
		$this->updateHandler->handleUpdate($update);

		$response_json = file_get_contents($this->botOutputFile);
		assert($response_json !== false);
		assert(strlen($response_json) > 0);

		$response_json = trim($response_json);
		$sentMessages_json = explode("\n\n", $response_json);

		$sentMessages = array();
		foreach($sentMessages_json as $message_json){
			$currentMessage = json_decode(trim($message_json));
			assert(json_last_error() === JSON_ERROR_NONE);

			$sentMessages[] = $currentMessage;
		}

		$result = array(
			'code'		=> http_response_code(),
			'sentMessages'	=> $sentMessages
		);
		
		return $result;
	}


}



















