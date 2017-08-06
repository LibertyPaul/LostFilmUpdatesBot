<?php

namespace core;

require_once(__DIR__.'/OutgoingMessage.php');

class DirectedOutgoingMessage{
	private $user_id;
	private $outgoingMessage;

	private $nextMessage;

	public function __construct($user_id, OutgoingMessage $outgoingMessage){
		assert(is_int($user_id));
		
		$this->user_id = $user_id;
		$this->outgoingMessage = $outgoingMessage;
	}

	public function appendMessage(DirectedOutgoingMessage $message){
		assert($message !== null);

		$current = $this;

		while($current->nextMessage !== null){
			$current = $current->nextMessage;
		}

		$current->nextMessage = $message;
	}

	public function getUserId(){
		return $this->user_id;
	}

	public function getOutgoingMessage(){
		return $this->outgoingMessage;
	}

	public function nextMessage(){
		return $this->nextMessage;
	}
}
