<?php

require_once(__DIR__.'/config/config.php');
require_once(__DIR__.'/config/stuff.php');
require_once(__DIR__.'/HTTPRequesterInterface.php');
require_once(__DIR__.'/Exceptions/StdoutTextException.php');
require_once(__DIR__.'/Exceptions/UserBlockedBotException.php');

require_once(__DIR__.'/Tracer.php');
require_once(__DIR__.'/EchoTracer.php');


class TelegramBot_base{
	const tokenLength = 32;
	const expireTime = 3600;
	
	protected $pdo;
	protected $memcache;
	
	private $HTTPRequester;

	protected $botTracer;
	protected $sentMessagesTracer;
	
	protected function __construct(HTTPRequesterInterface $HTTPRequester){
		if(isset($HTTPRequester) === false){
			throw new StdoutTextException('$HTTPRequester should not be a null pointer');
		}
		
		$this->HTTPRequester = $HTTPRequester;
	
		$this->pdo = createPDO();
		$this->memcache = createMemcache();
		
		$this_ptr = $this;
		
		set_exception_handler(function(Exception $ex) use ($this_ptr){
			$this_ptr->exception_handler($ex);
		});
		
		try{
			$this->botTracer = new Tracer(__CLASS__);
		}
		catch(Exception $ex){
			echo '[CRITICAL] '.$ex;
			$this->botTracer = new EchoTracer();
		}

		try{
			$this->sentMessagesTracer = new Tracer('sentMessages');
		}
		catch(Exception $ex){
			$this->botTracer->logException('[TRACER ERROR]', $ex);
			$this->sentMessagesTracer = new EchoTracer();
		}
	}
	
	private function exception_handler(Exception $ex){
		if(method_exists($ex, 'release')){
			$ex->release();
		}
		else{	
			$this->botTracer->log('[UNKNOWN EXCEPTION]', $ex->getFile(), $ex->getLine(), $ex->getMessage());
		}
		exit;
	}
	
	private function getSendMessageURL(){
		return 'https://api.telegram.org/bot'.TELEGRAM_BOT_TOKEN.'/sendMessage';
	}
	
	protected function sendMessage($data){//should NOT throw TelegramException
		$content_json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK);
		
		$this->sentMessagesTracer->log('[OUTGOING MESSAGE]', __FILE__, __LINE__, PHP_EOL.$content_json);
		
		$result = null;
		
		try{
			$result = $this->HTTPRequester->sendJSONRequest($this->getSendMessageURL(), $content_json);
		}
		catch(HTTPException $HTTPException){
			$this->botTracer->log('[HTTP ERROR]', $HTTPException->getFile(), $HTTPException->getLine(), $HTTPException->getMessage());
		}
		
		$this->sentMessagesTracer->log('[OUTGOING MESSAGE]', __FILE__, __LINE__, 'Return code: '.$result['code']);
				
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
			$this->sentMessagesTracer->log('[OUTGOING PARTIAL MESSAGE]', __FILE__, __LINE__, PHP_EOL.$content_json);
			
			$result = $this->HTTPRequester->sendJSONRequest(
				$this->getSendMessageURL(),
				$content_json
			);
			
			$this->sentMessagesTracer->log('[OUTGOING PARTIAL MESSAGE]', __FILE__, __LINE__, 'Return code: '.$result['code']);
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







