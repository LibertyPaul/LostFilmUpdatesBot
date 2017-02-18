<?php

require_once(__DIR__.'/TelegramBot.php');
require_once(__DIR__.'/ConversationStorage.php');
require_once(__DIR__.'/Tracer.php');
require_once(__DIR__.'/config/stuff.php');
require_once(__DIR__.'/Botan/Botan.php');

class UpdateHandler{
	private $tracer;
	private $memcache;
	private $botFactory;
	private $botan;

	public function __construct(TelegramBotFactoryInterface $botFactory){
		$this->tracer = new Tracer(__CLASS__);
		
		try{
			$this->memcache = createMemcache();
		}
		catch(Exception $ex){
			$this->tracer->logException('[ERROR]', $ex);
			throw $ex;
		}

		assert($botFactory !== null);
		$this->botFactory = $botFactory;

		$this->botan = new Botan(BOTAN_API_KEY);
	}

	private function getLastUpdateId(){ // TODO: move to the DB in order to be able to lock it
		return intval($this->memcache->get(MEMCACHE_LATEST_UPDATE_ID_KEY));
	}

	private function setLastUpdateId($value){
		assert(is_int($value));

		$current = $this->getLastUpdateId();
		if($value <= $current){
			$this->tracer->log('[ERROR]', __FILE__, __LINE__, "New update_id($value) is less or equal with current($current)");
			throw new RuntimeException("New update_id($value) is less than current($current)");
		}

		$this->memcache->set(MEMCACHE_LATEST_UPDATE_ID_KEY, $value);
	}

	private function verifyUpdateId($update_id){
		assert(is_int($update_id));
		return $update_id > $this->getLastUpdateId();
	}

	private function validateFields($update){
		return
			isset($update->update_id)			&&
			isset($update->message)				&&
			isset($update->message->from)		&&
			isset($update->message->from->id)	&&
			isset($update->message->chat)		&&
			isset($update->message->chat->id)	&&
			isset($update->message->text);
	}

	private function normalizeFields($update){
		$result = clone $update;
		
		$result->update_id = intval($result->update_id);
		$result->message->from->id = intval($result->message->from->id);
		$result->message->chat->id = intval($result->message->chat->id);

		return $result;
	}

	private function extractCommand($text){
		$regex = '/\/(\w+)/';
		$matches = array();
		$res = preg_match($regex, $text, $matches);
		if($res === false){
			$this->tracer->log('[ERROR]', __FILE__, __LINE__, 'preg_match error: '.preg_last_error());
			throw new LogicException('preg_match error: '.preg_last_error());
		}
		if($res === 0){
			$this->tracer->log('[ERROR]', __FILE__, __LINE__, "Invalid command '$text'");
			return $text;
		}

		return $matches[1];
	}

	private function validateData($update){
		return 
			$this->verifyUpdateId($update->update_id) &&
			$update->message->from->id !== $update->message->chat->id;
	}

	private function sendToBotan($message, $event){
		$message_assoc = json_decode(json_encode($message), true);
		$this->botan->track($message_array, $eventName);
	}

	private function handleMessage($message){
		try{
			$conversationStorage = new ConversationStorage($update->message->from->id);
			
			if($conversationStorage->getConversationSize() === 0){
				$message->text = $this->extractCommand($message->text);
			}
			
			$conversationStorage->insertMessage($message->text);

			$bot = $this->botFactory->createBot($message->from->id);
			$bot->incomingMessage($conversationStorage);
		}
		catch(TelegramException $ex){
			$this->tracer->logException('[TELEGRAM EXCEPTION]', $ex);
			$ex->release();
		}
		catch(Exception $ex){
			$this->tracer->logException('[ERROR]', $ex);
		}
		
		try{
			$this->sendToBotan($message, $conversationStorage->getConversation[0]);
		}
		catch(Exception $ex){
			$this->tracer->logException('[BOTAN ERROR]', $ex);
		}
	}

	public function handleUpdate($update){
		if($this->validateFields($update) === false){
			$this->tracer->log('[DATA ERROR]', __FILE__, __LINE__, 'Update is invalid:'.PHP_EOL.print_r($update, true));
			throw new RuntimeError('Invalid update');
		}

		$update = $this->normalizeFields($update);

		if($this->validateData($update) === false){
			$this->tracer->log('[ERROR]', __FILE__, __LINE__, 'Invalid update data: Last id: '.$this->getLastUpdateId().PHP_EOL.print_r($update, true));
			throw new RuntimeError('Invalid update_id'); // TODO: check if we should gently skip in such case
		}

		$this->handleMessage($update->message);
	}

}










