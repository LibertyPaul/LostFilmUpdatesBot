<?php

namespace TelegramAPI;

require_once(__DIR__.'/../lib/HTTPRequester/HTTPRequesterInterface.php');
require_once(__DIR__.'/../lib/Tracer/Tracer.php');
require_once(__DIR__.'/OutgoingMessage.php');
require_once(__DIR__.'/../lib/VelocityController/VelocityController.php');

class TelegramAPI{
	private $HTTPRequester;
	private $tracer;
	private $botToken;
	private $velocityController;

	const MAX_MESSAGE_JSON_LENGTH = 4000; // 4163 in fact. Have no idea why.
	
	public function __construct($botToken, \HTTPRequesterInterface $HTTPRequester){
		assert(is_string($botToken));
		$this->botToken = $botToken;

		assert($HTTPRequester !== null);
		$this->HTTPRequester = $HTTPRequester;
	
		$this->tracer = new \Tracer(__CLASS__);

		$this->velocityController = new \VelocityController(__CLASS__);
	}
	
	private function getSendMessageURL(){
		return 'https://api.telegram.org/bot'.$this->botToken.'/sendMessage';
	}

	private function waitForVelocity($user_id){
		while($this->velocityController->isSendingAllowed($user_id) === false){
			$res = time_nanosleep(0, 500000000); // 0.5s
			if($res !== true){
				$this->tracer->logError(
					'[PHP]', __FILE__, __LINE__,
					'time_nanosleep has failed'.PHP_EOL.print_r($res)
				);
			}
		}
	}

	private static function createKeyboard($options){
		$rowSize = 2;
		$keyboard = array();
		$currentRow = array();
		$currentRowPos = 0;
		foreach($options as $option){
			$currentRow[] = $option;
			if(++$currentRowPos % $rowSize == 0){
				$keyboard[] = $currentRow;
				$currentRow = array();
			}
		}
		if(empty($currentRow) === false)
			$keyboard[] = $currentRow;
		return $keyboard;
	}
	
	public function send(
		$telegram_id		,
		$text				,
		$textContainsHTML	,
		$URLExpandEnabled	,
		$responseOptions
	){
		assert(is_int($telegram_id));
		$this->waitForVelocity($telegram_id);

		$request = array(
			'chat_id'		=> $telegram_id,
			'text'			=> $text,
		);

		if($textContainsHTML){
			$request['parse_mode'] = 'HTML';
		}

		if($URLExpandEnabled === false){
			$request['disable_web_page_preview'] = true;
		}

		if(empty($responseOptions)){
			$request['reply_markup'] = array('remove_keyboard' => true);
		}
		else{
			$request['reply_markup'] = array(
				'keyboard' => self::createKeyboard($responseOptions)
			);
		}
		
		$request_json = json_encode($request, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
		if($request_json === false){
			$this->tracer->logError(
				'[JSON]', __FILE__, __LINE__,
				'json_encode has failed on:'.PHP_EOL.print_r($request, true)
			);
			throw new \RuntimeException('json_encode has failed on:'.print_r($request, true));
		}				
		
		$URL = $this->getSendMessageURL();
		try{
			$result = $this->HTTPRequester->sendJSONRequest($URL, $request_json);
		}
		catch(\HTTPException $HTTPException){
			$this->tracer->logException('[HTTP ERROR]', __FILE__, __LINE__, $HTTPException);
			throw $HTTPException;
		}
				
		return $result;
	}
	/*
	public function sendTextByLines($messageData, array $lines, $eol){
		$emptyMessage = json_encode($messageData);
		$emptyMessageLength = strlen($emptyMessage);
		
		$currentMessage = "";
		$bufferLength = $emptyMessageLength + strlen($currentMessage);
		
		$messages = array();
		
		foreach($lines as $str){
			$nextMessageLength = strlen($str) + strlen($eol);
			if($bufferLength > self::MAX_MESSAGE_JSON_LENGTH){
				$this->sentMessagesTracer->logWarning(
					'[DATA]', __FILE__, __LINE__,
					"Too long line: '$str'"
				);

				$str = substr($str, 0, self::MAX_MESSAGE_JSON_LENGTH - strlen($eol));
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

		foreach($messages as $request_json){
			$this->sentMessagesTracer->logEvent(
				'[OUTGOING PARTIAL MESSAGE]', __FILE__, __LINE__,
				PHP_EOL.$request_json
			);
			
			$result = $this->HTTPRequester->sendJSONRequest(
				$this->getSendMessageURL(),
				$request_json
			);
			
			$this->sentMessagesTracer->logEvent(
				'[OUTGOING PARTIAL MESSAGE]', __FILE__, __LINE__,
				"Return code: ($result[code])"
			);
		}
	}
	*/
}







