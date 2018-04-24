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

	private static function findLoop(self $lhs = null, self $rhs = null){
		$current = $rhs;
		while($current !== null){
			if($current === $lhs){
				return true;
			}
			$current = $current->nextMessage();
		}

		$current = $lhs;
		while($current !== null){
			if($current === $rhs){
				return true;
			}
			$current = $current->nextMessage();
		}

		return false; 
		# Not sure if this check is redundant. It might be possible to optimize it.
	}

	public function appendMessage(self $message = null){
		if(self::findLoop($this, $message)){
			throw new \LogicException(
				'Loop was found in message chains:'.PHP_EOL.
				'#1:'.PHP_EOL.
				print_r($this, true).PHP_EOL.
				'#2:'.PHP_EOL.
				print_r($message, true)
			);
		}

		if($this->nextMessage !== null){
			$this->nextMessage->appendMessage($message);
		}
		else{
			$this->nextMessage = $message;
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
