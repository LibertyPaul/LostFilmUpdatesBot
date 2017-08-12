<?php

namespace core;

class IncomingMessage{
	private $user_id;
	private $text;
	private $rawMessage;
	private $update_id;

	public function __construct($user_id, $text, $rawMessage = null, $update_id = null){
		assert(is_int($user_id));
		assert(is_string($text));

		$this->user_id = $user_id;
		$this->text = $text;
		$this->rawMessage = $rawMessage;
		$this->update_id = $update_id;
	}

	public function getUserId(){
		return $this->user_id;
	}

	public function getText(){
		return $this->text;
	}

	public function getRawMessage(){
		return $this->rawMessage;
	}

	public function getUpdateId(){
		return $this->update_id;
	}
}
