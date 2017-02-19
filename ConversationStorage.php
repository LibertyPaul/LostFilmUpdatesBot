<?php

require_once(__DIR__.'/config/config.php');
require_once(__DIR__.'/config/stuff.php');
require_once(__DIR__.'/Tracer.php');


class ConversationStorage{
	private $telegram_id;
	private $memcache;
	private $tracer;
	private $conversation;

	public function __construct($telegram_id){
		assert(is_int($telegram_id));
		$this->telegram_id = $telegram_id;

		$this->tracer = new Tracer(__CLASS__);

		try{
			$this->memcache = createMemcache();
		}
		catch(Exception $ex){
			$this->tracer->logException('[FATAL]', $ex);
			throw $ex;
		}

		$this->fetchConversation();
	}

	private function getMemcacheKey(){
		return MEMCACHE_MESSAGE_CHAIN_PREFIX.$this->telegram_id;
	}
	
	private function fetchConversation(){
		$conversation_serialized = $this->memcache->get($this->getMemcacheKey());

		if($conversation_serialized !== false){
			$this->conversation = unserialize($conversation_serialized);
		}
		else{
			$this->conversation = array();
		}
	}

	private function commitConversation(){
		$conversation_serialized = serialize($this->conversation);

		$res = $this->memcache->set($this->getMemcacheKey(), $conversation_serialized);
		if($res === false){
			$this->tracer->log('[FATAL]', __FILE__, __LINE__, 'memcache->set has failed');
			throw new RuntimeException('memcache->set has failed');
		}
	}

	public function getConversation(){
		return $this->conversation;
	}

	public function getFirstMessage(){
		if($this->getConversationSize() < 1){
			throw new RuntimeException('ConversationStorage is empty');
		}
		return $this->conversation[0];
	}

	public function getLastMessage(){
		if($this->getConversationSize() < 1){
			throw new RuntimeException('ConversationStorage is empty');
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
		$this->memcache->delete($this->getMemcacheKey());
		$this->conversation = array();
	}

	public function deleteLastMessage(){
		array_pop($this->conversation);
		$this->commitConversation();
	}
}
