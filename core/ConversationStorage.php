<?php

namespace core;

require_once(__DIR__.'/../lib/KeyValueStorage/MemcachedStorage.php');
require_once(__DIR__.'/../lib/Tracer/Tracer.php');
require_once(__DIR__.'/BotPDO.php');
require_once(__DIR__.'/../lib/Config.php');


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
		$conversation_serialized = $this->storage->getValue($this->user_id);

		if($conversation_serialized !== null){
			$this->conversation = unserialize($conversation_serialized);
		}
		else{
			$this->conversation = array();
		}
	}

	private function commitConversation(){
		$conversation_serialized = serialize($this->conversation);
		$res = $this->storage->setValue($this->user_id, $conversation_serialized);
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

	public function getLastMessage(){
		if($this->getConversationSize() < 1){
			throw new \RuntimeException('ConversationStorage is empty');
		}
		return $this->conversation[count($this->conversation) - 1];
	}

	public function getConversationSize(){
		return count($this->conversation); // O(1)
	}

	public function insertMessage($text){
		assert(is_string($text));
		$this->conversation[] = $text;
		$this->commitConversation();
	}

	public function deleteConversation(){
		$this->storage->deleteValue($this->user_id);
		$this->conversation = array();
	}

	public function deleteLastMessage(){
		array_pop($this->conversation);
		$this->commitConversation();
	}
}
