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

	private function findLoop(self $newNode = null){
		$current = $this;
		do{
			if($current === $lhs){
				return true;
			}

			$current = $current->nextMessage();
		}while($current !== null);

		return false; 
	}

	public function appendMessage(self $message = null){
		if($this->findLoop($message)){
			throw new \LogicException(
				'Loop was detected:'.PHP_EOL.
				'#1:'.PHP_EOL.
				print_r($this, true).PHP_EOL.
				'#2:'.PHP_EOL.
				print_r($message, true)
			);
		}

		if($this->nextMessage !== null){
			return $this->nextMessage->appendMessage($message);
		}
		else{
			$this->nextMessage = $message;
			return $this;
		}
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

	public function __toString(){
		$nextMessagePresent = $this->nextMessage() === null ? 'N' : 'Y';

		$result  = '##### [Directed Outgoing Message] #####'			.PHP_EOL;
		$result .= sprintf('User Id:      [%s]', $this->getUserId())	.PHP_EOL;
		$result .= sprintf('Next Message: [%s]', $nextMessagePresent)	.PHP_EOL;
		$result .= 'Message Body:'										.PHP_EOL;
		$result .= $this->getOutgoingMessage()							.PHP_EOL;
		$result .= '#######################################'			.PHP_EOL;

		return $result;
	}
}
