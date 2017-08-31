<?php

namespace TelegramAPI;

require_once(__DIR__.'/../core/InlineOption.php');
require_once(__DIR__.'/../lib/HTTPRequester/HTTPRequesterInterface.php');
require_once(__DIR__.'/../lib/Tracer/Tracer.php');
require_once(__DIR__.'/OutgoingMessage.php');
require_once(__DIR__.'/VelocityController.php');
require_once(__DIR__.'/VelocityControllerFactory.php');

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

		$this->velocityController = VelocityControllerFactory::getMemcachedBasedController(__CLASS__);
	}
	
	private function getSendMessageURL(){
		return 'https://api.telegram.org/bot'.$this->botToken.'/sendMessage';
	}

	private function getGetFileURL($file_id){
		$format = 'https://api.telegram.org/bot%s/getFile?file_id=%s';
		return sprintf($format, $this->botToken, $file_id);
	}

	private function getDownloadFileURL($file_path){
		$format = 'https://api.telegram.org/file/bot%s/%s';
		return sprintf($format, $this->botToken, $file_path);
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

	private static function createKeyboard(array $options){
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

	private static function createInlineKeyboard(array $inlineOptions){
		$keyboard = array();
		$row = array();

		foreach($inlineOptions as $option){
			switch($option->getType()){
				case \core\InlineOptionType::Option:
					$row[] = array(
						'text'			=> $option->getText(),
						'callback_data'	=> $option->getPayload()
					);
					
					break;

				case \core\InlineOptionType::ExternalLink:
					$row[] = array(
						'text'	=> $option->getText(),
						'url'	=> $option->getPayload()
					);

					break;

				case \core\InlineOptionType::ShareButton:
					$row[] = array(
						'text'					=> $option->getText(),
						'switch_inline_query'	=> $option->getPayload()
					);

					break;
			}
		}

		$keyboard[] = $row;
		return $keyboard;
	}
		
	
	public function send(
		$telegram_id,
		$text,
		$textContainsHTML,
		$URLExpandEnabled,
		array $responseOptions = null,
		array $inlineOptions = null
	){
		assert(is_int($telegram_id));
		assert(is_string($text));

		$request = array(
			'chat_id'	=> $telegram_id,
			'text'		=> $text
		);

		if($textContainsHTML){
			$request['parse_mode'] = 'HTML';
		}

		if($URLExpandEnabled === false){
			$request['disable_web_page_preview'] = true;
		}

		if(empty($responseOptions) && empty($inlineOptions)){
			$request['reply_markup'] = array('remove_keyboard' => true);
		}

		if(empty($responseOptions) === false){
			$request['reply_markup'] = array(
				'keyboard' => self::createKeyboard($responseOptions)
			);
		}

		if(empty($inlineOptions) === false){
			$request['reply_markup'] = array(
				'inline_keyboard' => self::createInlineKeyboard($inlineOptions)
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
			$this->waitForVelocity($telegram_id);
			$result = $this->HTTPRequester->sendJSONRequest($URL, $request_json);
		}
		catch(\HTTPException $HTTPException){
			$this->tracer->logException('[HTTP ERROR]', __FILE__, __LINE__, $HTTPException);
			throw $HTTPException;
		}
				
		return $result;
	}

	public function downloadVoiceMessage($file_id){
		$getFileURL = $this->getGetFileURL($file_id);
		$result = $this->HTTPRequester->sendGETRequest($getFileURL);

		if($result['code'] >= 400){
			$this->tracer->logError(
				'[TELEGRAM API]', __FILE__, __LINE__,
				"getFile call has failed with code=[$result[code]]. Response text:".PHP_EOL.
				$result['value']
			);

			throw new \RuntimeException('Failed to get file info');
		}

		$File = json_decode($result['value']);
		if($File === false){
			$this->tracer->logError(
				'[JSON]', __FILE__, __LINE__,
				"Unable to parse Telegram getFile response. Raw text: '$result[value]'"
			);

			throw new \RuntimeException('Failed to parse getFile response');
		}

		$this->tracer->logDebug(
			'[TELEGRAM API]', __FILE__, __LINE__,
			'getFile has returned:'.PHP_EOL.print_r($File, true)
		);

		assert(isset($File->ok));
		assert($File->ok === 1);
		assert(isset($File->result));
		assert(isset($File->result->file_path));
		
		$downloadURL = $this->getDownloadFileURL($File->result->file_path);
		$result =$this->HTTPRequester->sendGETRequest($downloadURL);

		if($result['code'] >= 400){
			$this->tracer->logError(
				'[TELEGRAM API]', __FILE__, __LINE__,
				"File download has failed with code=[$result[code]]. File id=[$file_id]".PHP_EOL.
				"Download URL=[$downloadURL], response:".PHP_EOL.
				$result['value']
			);

			throw new \RuntimeException('Failed to download file');
		}

		return $result['value'];
	}
}







