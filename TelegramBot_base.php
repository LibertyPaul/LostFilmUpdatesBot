<?php

require_once(__DIR__.'/config/config.php');
require_once(__DIR__.'/config/stuff.php');
require_once(__DIR__.'/HTTPRequesterInterface.php');
require_once(__DIR__.'/Exceptions/StdoutTextException.php');
require_once(__DIR__.'/Exceptions/UserBlockedBotException.php');



class TelegramBot_base{
	const tokenLength = 32;
	const expireTime = 3600;
	
	protected $sql;
	protected $pdo;
	
	private $HTTPRequester;
	
	protected function __construct(HTTPRequesterInterface $HTTPRequester){
		if(isset($HTTPRequester) === false){
			throw new StdoutTextException('$HTTPRequester should not be a null pointer');
		}
		
		$this->HTTPRequester = $HTTPRequester;
	
		$this->sql = createSQL();
		$this->pdo = createPDO();
		$this->memcache = createMemcache();
		
		$this_ptr = $this;
		
		set_exception_handler(function(Exception $ex) use ($this_ptr){
			$this_ptr->exception_handler($ex);
		});
		
		
	}
	
	private function logException($ex){
		$path = __DIR__."/../logs/uncaughtExceptions.json.txt";
		$log = createOrOpenLogFile($path);
		
		$str = json_encode($ex);
		$res = fwrite($log, $str);
		if($res === false)
			exit("uncaughtExceptions fwrite error");
			
		$res = fclose($log);
		if($res === false)
			exit("uncaughtExceptions fclose error");
		
	}
	
	private function exception_handler(Exception $ex){
		if(method_exists($ex, 'release')){
			$ex->release();
		}
		else{
			echo $ex->getMessage();
		}
		
		$this->logException($ex);
		exit;
	}
	
	private function getSendMessageURL(){
		return "https://api.telegram.org/bot".TELEGRAM_BOT_TOKEN."/sendMessage";
	}
	
	private function validateTelegramResponse($rawResponse){
		$response = json_decode($rawResponse);
		if($response === false){
			return array(
				'isValid' 	=> false,
				'reason'	=> 'json_decode error: '.json_last_error_msg()
			);
		}
		
		if(isset($response->ok) === false){
			return array(
				'isValid' 	=> false,
				'reason'	=> '$response->ok field is not found'
			);
		}
		
		if(is_bool($response->ok) === false){
			return array(
				'isValid' 	=> false,
				'reason'	=> '$response->ok field is not of boolean type'
			);
		}
		
		if($response->ok === false){
			return array(
				'isValid' 	=> false,
				'reason'	=> '$response->ok is false'
			);
		}
		
		return array(
			'isValid' => true
		);
	}
	
	protected function sendMessage($data){//should NOT throw TelegramException
		$path = __DIR__."/logs/sentMessages.txt";
		$log = createOrOpenLogFile($path);
		
		$content_json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK);
		
		$res = fwrite($log, "[".date('d.m.Y H:i:s')."]\t$content_json");
		if($res === false){
			throw new StdoutTextException("log fwrite1 error");
		}
	
		$rawResponse = null;
		
		try{
			$rawResponse = $this->HTTPRequester->sendJSONRequest($this->getSendMessageURL(), $content_json);
		}
		catch(HTTPException $HTTPException){
			$respCode = $HTTPException->getCode();
			$res = fwrite($log, "ERROR $respCode\n");
			if($res === false){
				throw new StdoutTextException("log fwrite2 error");
			}
			
			switch($respCode){
			case 403:
				throw new UserBlockedBotException("Destination chat_id: $data[chat_id]");
			default:
				throw new StdoutTextException("Unknown HTTP response code: $respCode");
			}
		}
		
		$validationResult = $this->validateTelegramResponse($rawResponse['value']);
		if($validationResult['isValid'] === true){
			$res = fwrite($log, "SUCCESS\n\n");
			if($res === false){
				throw new StdoutTextException("log fwrite3 error");
			}
		}
		else{
			throw new StdoutTextException('Telegram response validation failed: '.$validationResult['reason']);
		}
				
		$res = fclose($log);
		if($res === false){
			throw new StdoutTextException("log fclose error");
		}
		
		return $rawResponse;
	}
	
	protected function sendTextByLines($messageData, array $lines, $eol){
		$emptyMessage = json_encode($messageData);
		$emptyMessageLength = strlen($emptyMessage);
		
		$currentMessage = "";
		$bufferLength = $emptyMessageLength + strlen($currentMessage);
		
		$messages = array();
		
		foreach($lines as $str){
			$nextMessageLength = strlen($str) + strlen($eol);
			if($bufferLength > MAX_MESSAGE_JSON_LENGTH){
				throw new Exception("Слишком длинная строка");
			}
			else if($bufferLength + $nextMessageLength > MAX_MESSAGE_JSON_LENGTH){
				$messageData['text'] = $currentMessage;
				$messages[] = json_encode($messageData);
				
				$currentMessage = "";
				$bufferLength = $emptyMessageLength + strlen($currentMessage);
			}
			else{
				$nextMessage = $str.$eol;
				$currentMessage .= $nextMessage;
				$bufferLength += strlen($nextMessage);
			}
		}
		
		if($bufferLength !== 0){
			$messageData['text'] = $currentMessage;
			$messages[] = json_encode($messageData);
		}
		
		foreach($messages as $content_json){
			try{
				$rawResponse = $this->HTTPRequester->sendJSONRequest($this->getSendMessageURL(), $content_json);
				
				$validationResult = $this->validateTelegramResponse($rawResponse['value']);
				
				if($validationResult['isValid'] === false){
					throw new StdoutTextException('Telegram response validation failed: '.$validationResult['reason']);
				}
			}
			catch(HTTPException $HTTPException){
				switch($HTTPException->getCode()){
				case 403:
					throw new UserBlockedBotException("Destination chat_id: $data[chat_id]");
				default:
					throw new StdoutTextException("Unknown HTTP response code: $respCode");
				}
			}
		}
	}
	
	
	protected function createKeyboard($items){
		$rowSize = 2;
		$keyboard = array();
		$currentRow = array("/cancel");
		$currentRowPos = 1;
		foreach($items as $item){
			$currentRow[] = $item;
			if(++$currentRowPos % $rowSize == 0){
				$keyboard[] = $currentRow;
				$currentRow = array();
			}
		}
		if(count($currentRow) !== 0)
			$keyboard[] = $currentRow;
		return $keyboard;
	}
}







