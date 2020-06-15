<?php

namespace core;

require_once(__DIR__.'/../lib/KeyValueStorage/MemcachedStorage.php');
require_once(__DIR__.'/../lib/Tracer/TracerFactory.php');
require_once(__DIR__.'/BotPDO.php');
require_once(__DIR__.'/../lib/Config.php');
require_once(__DIR__.'/IncomingMessage.php');


abstract class ConversationStorageInsertPosition{
	const Front	= 1;
	const Back	= 2;

	public static function toString($position){
		switch($position){
			case 1:
				return "Front";

			case 2:
				return "Back";

			default:
				return "<Incorrect Position ($position)>";
		}
	}	
}


class ConversationStorage{
	private $user_id;
	private $storage;
	private $tracer;
	private $conversation;

	const MEMCACHE_STORE_TIME = 86400; // 1 day

	public function __construct(int $user_id, \Config $config, \PDO $pdo){
		$this->user_id = $user_id;
		$this->tracer = \TracerFactory::getTracer(__CLASS__, $pdo);

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
		catch(\Throwable $ex){
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

		foreach($this->conversation as $incomingMessage){
			if($incomingMessage instanceof IncomingMessage === false){
				$this->tracer->logfError(
					'[o]', __FILE__, __LINE__,
					'Got invalid object from memcache [%s][%s]',
					gettype($incomingMessage),
					strval($incomingMessage)
				);

				$this->deleteConversation();

				$this->tracer->logDebug(
					'[o]', __FILE__, __LINE__,
					'Whole conversation was erased.'
				);

				throw new \RuntimeException('Conversation is not valid.');
			}
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

		$this->tracer->logDebug('[o]', __FILE__, __LINE__, 'Conversation was committed.');
		$this->tracer->logDebug('[o]', __FILE__, __LINE__, 'Conversation one-line: '.$this);
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
		return count($this->conversation);
	}

	private function insertMessage(IncomingMessage $incomingMessage, $position){
		$this->tracer->logfDebug(
			'[o]', __FILE__, __LINE__,
			"Inserting message to %s:\n%s",
			ConversationStorageInsertPosition::toString($position),
			$incomingMessage
		);

		switch($position){
			case ConversationStorageInsertPosition::Front:
				array_unshift($this->conversation, $incomingMessage);
				break;

			case ConversationStorageInsertPosition::Back:
				array_push($this->conversation, $incomingMessage);
				break;

			default:
				$this->tracer->logfError('[o]', __FILE__, __LINE__, 'Incorrect insertMessage position.');
				throw new \RuntimeError('Incorrect insertMessage position.');
		}

		$this->commitConversation();

		$this->tracer->logDebug('[o]', __FILE__, __LINE__, 'Done.');
	}

	public function appendMessage(IncomingMessage $incomingMessage){
		return $this->insertMessage($incomingMessage, ConversationStorageInsertPosition::Back);
	}

	public function prependMessage(IncomingMessage $incomingMessage){
		return $this->insertMessage($incomingMessage, ConversationStorageInsertPosition::Front);
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

	public function __toString(){
		$messageTexts = array();
		foreach($this->conversation as $incomingMessage){
			$messageTexts[] = $incomingMessage->getText();
		}

		return "[User: $this->user_id] : [".join('] --> [', $messageTexts)."]";
	}
}
