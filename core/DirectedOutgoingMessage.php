<?php

namespace core;

require_once(__DIR__.'/OutgoingMessage.php');

require_once(__DIR__.'/../lib/DAL/Users/UsersAccess.php');
require_once(__DIR__.'/../lib/DAL/Users/User.php');

class DirectedOutgoingMessage{
	private $user;
	private $outgoingMessage;

	private $nextMessage;

	public function __construct(\DAL\User $user, OutgoingMessage $outgoingMessage){
		$this->user = $user;
		$this->outgoingMessage = $outgoingMessage;
	}

	private function findLoop(self $newNode): bool {
		$current = $newNode;

		while($current !== null){
			if($current === $this){
				return true;
			}

			$current = $current->nextMessage();
		}

		return false; 
	}

	public function appendMessage(self $message = null): DirectedOutgoingMessage {
		if($message === null){
			return $this;
		}

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

	public function getUser(): \DAL\User {
		return $this->user;
	}

	public function getOutgoingMessage(): OutgoingMessage {
		return $this->outgoingMessage;
	}

	public function nextMessage(): ?DirectedOutgoingMessage {
		return $this->nextMessage;
	}

	public function __toString(): string {
		$nextMessagePresent = $this->nextMessage() === null ? 'N' : 'Y';

		$result  = '##### [Directed Outgoing Message] #####'			.PHP_EOL;
		$result .= $this->getUser()										.PHP_EOL;
		$result .= sprintf('Next Message: [%s]', $nextMessagePresent)	.PHP_EOL;
		$result .= 'Message Body:'										.PHP_EOL;
		$result .= $this->getOutgoingMessage()							.PHP_EOL;
		$result .= '#######################################'			.PHP_EOL;

		return $result;
	}
}
