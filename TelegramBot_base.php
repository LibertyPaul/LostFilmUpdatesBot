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
	protected $memcache;
	
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
		assert(fwrite($log, $str));
		assert(fclose($log));
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
	
	protected function sendMessage($data){//should NOT throw TelegramException
		$path = __DIR__."/logs/sentMessages.txt";
		$log = createOrOpenLogFile($path);
		
		$content_json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK);
		
		assert(fwrite($log, "[".date('d.m.Y H:i:s')."]\t$content_json"));
		
		$result = null;
		
		try{
			$result = $this->HTTPRequester->sendJSONRequest($this->getSendMessageURL(), $content_json);
		}
		catch(HTTPException $HTTPException){
			assert(fwrite($log, 'ERROR '.$HTTPException->getMessage().PHP_EOL));
		}
		
		if($result['code'] === 200){
			assert(fwrite($log, "SUCCESS\n\n"));
		}
				
		assert(fclose($log));
		
		return $result;
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
				throw new Exception("One of the rows in too long");
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
			$response = $this->HTTPRequester->sendJSONRequest(
				$this->getSendMessageURL(),
				$content_json
			);

			if($response['code'] !== 200){
				throw new Exception('Failed part-of-message sending attempt. HTTP code: '.$response['code']);
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







