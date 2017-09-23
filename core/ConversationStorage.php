<?php

namespace core;

require_once(__DIR__.'/../lib/KeyValueStorage/MemcachedStorage.php');
require_once(__DIR__.'/../lib/Tracer/Tracer.php');
require_once(__DIR__.'/BotPDO.php');
require_once(__DIR__.'/../lib/Config.php');
require_once(__DIR__.'/IncomingMessage.php');


class ConversationStorage{
	private $user_id;
	private $storage;
	private $tracer;
	private $conversation;

	const MEMCACHE_STORE_TIME = 86400; // 1 day

	public function __construct($user_id){
		$this->tracer = new \Tracer(__CLASS__);

		assert(is_int($user_id));
		$this->user_id = $user_id;

		$config = new \Config(\BotPDO::getInstance());
		$keyPrefix = $config->getValue('Conversation Storage', 'Key Prefix');
		if($keyPrefix === null){
			$this->tracer->logWarning(
				'[CONFIG]', __FILE__, __LINE__,
				'Parameter [Conversation Storage][Key Prefix] does not exist. Using "".'
			);

			$keyPrefix = '';
		}

		try{
			$this->storage = new \MemcachedStorage(
				$keyPrefix,
				self::MEMCACHE_STORE_TIME
			);
		}
		catch(\Exception $ex){
			$this->tracer->logException('[MEMCACHE]', __FILE__, __LINE__, $ex);
			throw $ex;
		}

		$this->fetchConversation();
	}
	
	private function fetchConversation(){
		$this->tracer->logDebug('[o]', __FILE__, __LINE__, 'Fetching Conversation ...');

		$conversation_serialized = $this->storage->getValue($this->user_id);

		if($conversation_serialized !== null){
			$this->conversation = unserialize($conversation_serialized);
		}
		else{
			$this->conversation = array();
		}

		$this->tracer->logDebug(
			'[o]', __FILE__, __LINE__,
			'Fetched Conversation:'.PHP_EOL.
			print_r($this->conversation, true)
		);
	}

	private function commitConversation(){
		$this->tracer->logDebug(
			'[o]', __FILE__, __LINE__,
			'Committing Conversation:'.PHP_EOL.
			print_r($this->conversation, true)
		);

		$conversation_serialized = serialize($this->conversation);
		$res = $this->storage->setValue($this->user_id, $conversation_serialized);

		$this->tracer->logDebug('[o]', __FILE__, __LINE__, 'Conversation was committed');
	}

	public function getConversation(){
		return $this->conversation;
	}

	public function getFirstMessage(){
		if($this->getConversationSize() < 1){
			throw new \RuntimeException('ConversationStorage is empty');
		}

		return $this->conversation[0];
	}

	public function getMessage($number){
		assert(array_key_exists($number, $this->conversation));
		return $this->conversation[$number];
	}

	public function getLastMessage(){
		if($this->getConversationSize() < 1){
			throw new \RuntimeException('ConversationStorage is empty');
		}

		return $this->conversation[count($this->conversation) - 1];
	}

	public function getConversationSize(){
		return count($this->conversation); // O(1)
	}

	public function insertMessage(IncomingMessage $incomingMessage){
		$this->tracer->logDebug(
			'[o]', __FILE__, __LINE__,
			'Inserting message:'.PHP_EOL.
			$incomingMessage
		);

		$this->conversation[] = $incomingMessage;
		$this->commitConversation();

		$this->tracer->logDebug('[o]', __FILE__, __LINE__, 'Done.');
	}

	public function deleteConversation(){
		$this->tracer->logDebug('[o]', __FILE__, __LINE__, 'Deleting conversation ...');

		$this->storage->deleteValue($this->user_id);
		$this->conversation = array();

		$this->tracer->logDebug('[o]', __FILE__, __LINE__, 'Done.');
	}

	public function deleteLastMessage(){
		$this->tracer->logDebug('[o]', __FILE__, __LINE__, 'Deleting last message ...');

		array_pop($this->conversation);
		$this->commitConversation();
		
		$this->tracer->logDebug('[o]', __FILE__, __LINE__, 'Done.');
	}
}
