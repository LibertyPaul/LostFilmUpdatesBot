<?php

namespace core;

require_once(__DIR__.'/../UpdateHandler.php');
require_once(__DIR__.'/../UserCommand.php');
require_once(__DIR__.'/../IncomingMessage.php');
require_once(__DIR__.'/../BotPDO.php');

class MessageTester{
	private $user_id;
	private $botOutputFile;
	private $updateHandler;
	private $getResponseStatusQuery;

	public function __construct($user_id){
		assert(is_int($user_id));
		$this->user_id = $user_id;
		$this->botOutputFile = tempnam(sys_get_temp_dir(), 'MessageTester_');
		$this->updateHandler = new UpdateHandler();

		$pdo = \BotPDO::getInstance();
		$this->getResponseStatusQuery = $pdo->prepare('
			SELECT `statusCode`
			FROM `messagesHistory`
			WHERE `inResponseTo` = :request_id
		');
	}

	public function send($text, UserCommand $userCommand = null, $update_id = null){
		$message = new IncomingMessage(
			$user_id,
			$userCommand,
			$text,
			null,
			$update_id
		);

		$loggedRequestId = $this->updateHandler->processIncomingMessage($message);

		$this->getResponseStatusQuery->execute(
			array(
				':request_id' => $loggedRequestId
			)
		);

		$response = $this->getResponseStatusQuery->fetch();
		if($response === false){
			throw new \RuntimeException('Response was not found');
		}
	
		$statusCode = $res[0];

		$result = array(
			'code'			=> $statusCode,
			'sentMessages'	=> $sentMessages
		);
		
		return $result;
	}


}



















