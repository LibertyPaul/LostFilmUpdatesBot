<?php
require_once(__DIR__.'/Message.php');

class MessageList implements Iterator{
	private $messages;
	private $currentPos;

	public function __construct($initial = null){
		$this->messages = array();
		$this->currentPos = 0;

		if($initial !== null){
			$this->add($initial);
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

	public function rewind(){
		$this->currentPos = 0;
	}

	public function current(){
		if($this->valid() === false){
			throw new OutOfRangeException('Array size=['.count($this->messages).'], requested element index=['.$this->currentPos.']');
		}

		return $this->messages[$this->currentPos];
	}

	public function key(){
		return $this->currentPos;
	}

	public function next(){
		++$this->currentPos;
	}

	public function valid(){
		return $this->currentPos < count($this->messages);
	}
}
