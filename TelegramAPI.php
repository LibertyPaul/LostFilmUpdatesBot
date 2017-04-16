<?php
require_once(__DIR__.'/config/Config.php');
require_once(__DIR__.'/HTTPRequesterInterface.php');
require_once(__DIR__.'/Tracer.php');
require_once(__DIR__.'/Message.php');
require_once(__DIR__.'/MessageList.php');



class TelegramAPI{
	private $HTTPRequester;
	private $tracer;
	private $sentMessagesTracer;
	private $botToken;

	const MAX_MESSAGE_JSON_LENGTH = 4000; // 4163 in fact. Have no idea why.
	
	public function __construct($botToken, HTTPRequesterInterface $HTTPRequester){
		assert(is_string($botToken));
		$this->botToken = $botToken;

		assert($HTTPRequester !== null);
		$this->HTTPRequester = $HTTPRequester;
	
		$this->tracer = new Tracer(__CLASS__);
		$this->sentMessagesTracer = new Tracer('sentMessages');
	}
	
	private function getSendMessageURL(){
		return 'https://api.telegram.org/bot'.$this->botToken.'/sendMessage';
	}
	
	public function sendMessage(Message $message){
		$content_json = $message->toPrettyJSON();
		
		$this->sentMessagesTracer->logEvent('[OUTGOING MESSAGE]', __FILE__, __LINE__, PHP_EOL.$content_json);
		
		$URL = $this->getSendMessageURL();		
		try{
			$result = $this->HTTPRequester->sendJSONRequest($URL, $content_json);
		}
		catch(HTTPException $HTTPException){
			$this->tracer->logException('[HTTP ERROR]', __FILE__, __LINE__, $HTTPException);
			throw $HTTPException;
		}
		
		$this->sentMessagesTracer->logEvent('[OUTGOING MESSAGE]', __FILE__, __LINE__, 'Return code: '.$result['code']);
				
		return $result;
	}
	
	public function sendTextByLines($messageData, array $lines, $eol){
		$emptyMessage = json_encode($messageData);
		$emptyMessageLength = strlen($emptyMessage);
		
		$currentMessage = "";
		$bufferLength = $emptyMessageLength + strlen($currentMessage);
		
		$messages = array();
		
		foreach($lines as $str){
			$nextMessageLength = strlen($str) + strlen($eol);
			if($bufferLength > self::MAX_MESSAGE_JSON_LENGTH){
				throw new Exception("One of the rows in too long");
			}
			else if($bufferLength + $nextMessageLength > self::MAX_MESSAGE_JSON_LENGTH){
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
			$this->sentMessagesTracer->logEvent('[OUTGOING PARTIAL MESSAGE]', __FILE__, __LINE__, PHP_EOL.$content_json);
			
			$result = $this->HTTPRequester->sendJSONRequest(
				$this->getSendMessageURL(),
				$content_json
			);
			
			$this->sentMessagesTracer->logEvent('[OUTGOING PARTIAL MESSAGE]', __FILE__, __LINE__, 'Return code: '.$result['code']);
		}
	}
}







