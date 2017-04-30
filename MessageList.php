<?php
require_once(__DIR__.'/Message.php');

class MessageList{
	private $messages;

	public function __construct(Message $message = null){
		$this->messages = array();

		if($message !== null){
			$this->addMessage($message);
		}
	}

	private function addMessage(Message $message){
		if($message === null){
			throw new InvalidArgumentException('Null passed instead of Message');
		}

		$this->messages[] = $message;

		return $this;
	}

	private function addMessageList(MessageList $messageList){
		foreach($messageList->messages as $message){
			$this->addMessage($message);
		}

		return $this;
	}

	public function add($obj){
		if($obj instanceOf Message){
			return $this->addMessage($obj);
		}
		elseif($obj instanceOf MessageList){
			return $this->addMessageList($obj);
		}
		else{
			throw new InvalidArgumentException('$obj is of incorrect type: '.gettype($obj));
		}
	}

	public function getMessages(){
		return $this->messages;
	}
}
