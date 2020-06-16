<?php

namespace core;

require_once(__DIR__.'/../UpdateHandler.php');
require_once(__DIR__.'/../../lib/CommandSubstitutor/CoreCommand.php');
require_once(__DIR__.'/../IncomingMessage.php');
require_once(__DIR__.'/../BotPDO.php');

class MessageTester{
	private $user_id;
	private $botOutputFile;
	private $updateHandler;
	private $getResponseStatusQuery;

	public function __construct(int $user_id){
		$this->user_id = $user_id;
		$this->botOutputFile = tempnam(sys_get_temp_dir(), 'MessageTester_');
		$pdo = \BotPDO::getInstance();
		$this->updateHandler = new UpdateHandler($pdo);

		$this->getResponseStatusQuery = $pdo->prepare('
			SELECT `statusCode`
			FROM `messagesHistory`
			WHERE `inResponseTo` = :request_id
		');
	}

	public function send(
		$text,
		\CommandSubstitutor\CoreCommand $coreCommand = null,
		$update_id = null
	){
		$message = new IncomingMessage(
			$user_id,
			$coreCommand,
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



















