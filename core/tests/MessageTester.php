<?php

namespace core;

require_once(__DIR__.'/../UpdateHandler.php');
require_once(__DIR__.'/../IncomingMessage.php');

class MessageTester{

	private $username;
	private $firstName;
	private $lastName;
	
	private $user_id;
	private $botOutputFile;
	private $updateHandler;

	public function __construct(
		$user_id,
		$username = 'test username',
		$firstName = 'test first name',
		$lastName = 'test last name'
	){
		assert(is_int($user_id));
		$this->user_id = $user_id;
		$this->username = $username;
		$this->firstName = $firstName;
		$this->lastName = $lastName;
		
		$this->botOutputFile = tempnam(sys_get_temp_dir(), 'MessageTester_');

		$this->updateHandler = new UpdateHandler();
	}

	public function send($text, $update_id = null){
		$message = new IncomingMessage(
			$user_id,
			$text,
			null,
			$update_id
		);
		$this->updateHandler->processIncomingMessage($message);


		$result = array(
			'code'		=> http_response_code(),
			'sentMessages'	=> $sentMessages
		);
		
		return $result; # TODO: fix
	}


}



















