<?php

require_once(__DIR__."/config/config.php");
require_once(__DIR__."/config/stuff.php");
require_once(__DIR__."/Exceptions/StdoutTextException.php");


class TelegramBot_base{
	const tokenLength = 32;
	const expireTime = 3600;
	
	protected $sql;
	protected $pdo;
	
	protected function __construct(){
		$this->sql = createSQL();
		$this->pdo = createPDO();
		$this->memcache = createMemcache();
		
		$this_ptr = $this;
		
		set_exception_handler(function(Exception $ex) use ($this_ptr){
			$this_ptr->exception_handler($ex);
		});
		
		
	}
	
	protected function logException($ex){
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
	
	protected function exception_handler(Exception $ex){
		if(method_exists($ex, 'showErrorText')){
			$ex->showErrorText();
		}
		else{
			echo $ex->getMessage();
		}
		
		$this->logException($ex);
		exit;
	}
	
	protected function getHTTPCode($headers){
		$matches = array();
		$res = preg_match_all('/[\w]+\/\d\.\d (\d+) [\w]+/', $headers[0], $matches);
		
		$code = intval($matches[1][0]);
		return $code;
	}
		
	
	public function sendMessage($data){//should NOT throw TelegramException
		$path = __DIR__."/logs/sentMessages.txt";
		$log = createOrOpenLogFile($path);
		$data_json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK);
		$res = fwrite($log, "[".date('d.m.Y H:i:s')."]\t$data_json");
		if($res === false)
			throw new StdoutTextException("log fwrite1 error");
	
		$opts = array(
			'http' => array(
				'method' => 'POST',
				'header' => 'Content-type: application/json',
				'content' => $data_json
			)
		);
		
		
		$context = stream_context_create($opts);
		
		$res = file_get_contents("https://api.telegram.org/bot".TELEGRAM_BOT_TOKEN."/sendMessage", false, $context);
		if($res === false){
			$respCode = $this->getHTTPCode($http_response_header);
			
			$res = fwrite($log, "ERROR $respCode\n\n");
			if($res === false)
				throw new StdoutTextException("log fwrite2 error");
			
			switch($respCode){
			case 403:
				throw new StdoutTextException("Bot was blocked by user ".$data['chat_id'].". HTTP error 403");
			default:
				throw new StdoutTextException("sendMessage->file_get_contents unknown error $respCode: ".print_r($http_response_header, true).print_r($data, true));
			}
		}

		$message = json_decode($res);
		if($message === false)
			throw new StdoutTextException("sendMessage->json_decode error ".print_r($message, true).print_r($data, true));
		
		if(isset($message->ok) === false || $message->ok === false)
			throw new StdoutTextException("response is not OK: ".print_r($message, true).print_r($data, true));
		
		$respCode = $this->getHTTPCode($http_response_header);
		if($respCode !== 200){//если сервер телеграма не принял сообщение - попытаемся снова через некоторое время
			$res = fwrite($log, "FAIL: errcode = $respCode");
			if($res === false)
				throw new StdoutTextException("log fwrite2 error");
			throw new StdoutTextException("HTTP code !== 200\n".$http_response_header);
		}
		
		$res = fwrite($log, "SUCCESS\n\n");
		if($res === false)
			throw new StdoutTextException("log fwrite3 error");
		$res = fclose($log);
		if($res === false)
			throw new StdoutTextException("log fclose error");
		return $message->result;
	}
	
	public function sendTextByLines($messageData, $lines){
		$emptyMessage = json_encode($messageData);
		$emptyMessageLength = strlen($emptyMessage);
		
		$currentMessage = "";
		
		$messages = array();
		
		foreach($lines as $str){
			if($emptyMessageLength + strlen($currentMessage) > MAX_MESSAGE_JSON_LENGTH){
				throw new Exception("Слишком длинная строка");
			}
			else if($emptyMessageLength + strlen($currentMessage.$str) > MAX_MESSAGE_JSON_LENGTH){
				$messageData['text'] = $currentMessage;
				$currentMessage = "";
				$messages[] = $messageData;
			}
			else{				
				$currentMessage .= $str;
			}
			var_dump($currentMessage);
		}
		if(strlen($currentMessage) !== 0)
			$messageData['text'] = $currentMessage;
		$messages[] = $messageData;
		
		foreach($messages as $message){
			print_r($message);
			$this->sendMessage($message);
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







