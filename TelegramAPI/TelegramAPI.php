<?php

namespace TelegramAPI;

require_once(__DIR__.'/../core/InlineOption.php');
require_once(__DIR__.'/../core/MarkupType.php');
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
	
	public function __construct($botToken, \HTTPRequester\HTTPRequesterInterface $HTTPRequester){
		assert(is_string($botToken));
		$this->botToken = $botToken;
		$this->HTTPRequester = $HTTPRequester;
		$this->tracer = new \Tracer(__CLASS__);
		$this->velocityController = VelocityControllerFactory::getMemcachedBasedController(
			__CLASS__
		);
	}

	private function getBaseMethodURL($method){
		assert(is_string($method));
		return sprintf('https://api.telegram.org/bot%s/%s', $this->botToken, $method);
	}
	
	private function getDownloadFileURL($file_path){
		assert(is_string($file_path));
		return sprintf('https://api.telegram.org/file/bot%s/%s', $this->botToken, $file_path);
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
		int $telegram_id,
		string $text,
		\core\MarkupType $markupType,
		bool $URLExpandEnabled,
		array $responseOptions = null,
		array $inlineOptions = null
	){

		$request = array(
			'chat_id'	=> $telegram_id,
			'text'		=> $text
		);

		switch($markupType->get()){
			case \core\MarkupTypeEnum::NoMarkup:
				break;

			case \core\MarkupTypeEnum::HTML:
				$request['parse_mode'] = 'HTML';
				break;

			case \core\MarkupTypeEnum::Telegram:
				$request['parse_mode'] = 'Markdown';
				break;
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

		$requestJSON = json_encode($request, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE);
		if($requestJSON === false){
			$this->tracer->logError(
				'[JSON]', __FILE__, __LINE__,
				'json_encode has failed on:'.PHP_EOL.
				print_r($request, true)
			);

			throw new \RuntimeException('json_encode has failed on:'.print_r($request, true));
		}

		$requestProperties = new \HTTPRequester\HTTPRequestProperties(
			\HTTPRequester\RequestType::Post,
			\HTTPRequester\ContentType::JSON,
			$this->getBaseMethodURL('sendMessage'),
			$requestJSON
		);
		
		try{
			$this->waitForVelocity($telegram_id);
			$result = $this->HTTPRequester->request($requestProperties);
		}
		catch(\HTTPRequester\HTTPException $HTTPException){
			$this->tracer->logException('[HTTP ERROR]', __FILE__, __LINE__, $HTTPException);
			throw $HTTPException;
		}
				
		return $result;
	}

	public function forwardMessage($chat_id, $from_chat_id, $message_id, $silent = false){
		$payload = array(
			'chat_id'				=> $chat_id,
			'from_chat_id'			=> $from_chat_id,
			'message_id'			=> $message_id,
			'disable_notification'	=> $silent
		);

		$requestProperties = new \HTTPRequester\HTTPRequestProperties(
			\HTTPRequester\RequestType::Get,
			\HTTPRequester\ContentType::TextHTML,
			$this->getBaseMethodURL('forwardMessage'),
			$payload
		);

		$this->HTTPRequester->request($requestProperties);
	}

	public function downloadFile($file_id){
		$requestProperties = new \HTTPRequester\HTTPRequestProperties(
			\HTTPRequester\RequestType::Get,
			\HTTPRequester\ContentType::TextHTML,
			$this->getBaseMethodURL('getFile'),
			array('file_id' => $file_id)
		);

		$result = $this->HTTPRequester->request($requestProperties);

		if($result->getCode() >= 400){
			$this->tracer->logError(
				'[TELEGRAM API]', __FILE__, __LINE__,
				'getFile call has failed. Response:'.PHP_EOL.
				$result
			);

			throw new \RuntimeException('Failed to get file info');
		}

		$File = json_decode($result->getBody());
		if($File === false){
			$this->tracer->logError(
				'[JSON]', __FILE__, __LINE__,
				"Unable to parse Telegram getFile response:".PHP_EOL.
				$result
			);

			throw new \RuntimeException('Failed to parse getFile response');
		}

		$this->tracer->logDebug(
			'[TELEGRAM API]', __FILE__, __LINE__,
			'getFile has returned:'.PHP_EOL.
			print_r($File, true)
		);

		assert(isset($File->ok));
		if($File->ok === false){
			throw new \RuntimeException('Unable to retreive file info: '.$File->description);
		}

		assert(isset($File->result));
		assert(isset($File->result->file_path));
		
		$downloadURL = $this->getDownloadFileURL($File->result->file_path);

		$requestProperties = new \HTTPRequester\HTTPRequestProperties(
			\HTTPRequester\RequestType::Get,
			\HTTPRequester\ContentType::TextHTML,
			$downloadURL
		);

		$result = $this->HTTPRequester->request($requestProperties);

		if($result->getCode() >= 400){
			$this->tracer->logError(
				'[TELEGRAM API]', __FILE__, __LINE__,
				"File download has failed. File id=[$file_id]".PHP_EOL.
				$payload.PHP_EOL.
				$result
			);

			throw new \RuntimeException('Failed to download file');
		}

		return $result->getBody();
	}
}







