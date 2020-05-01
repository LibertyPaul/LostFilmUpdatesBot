<?php

namespace DAL;

require_once(__DIR__.'/../CommonAccess.php');
require_once(__DIR__.'/MessageHistoryBuilder.php');
require_once(__DIR__.'/MessageHistory.php');


class MessagesHistoryAccess extends CommonAccess{
	private $addMessageHistoryQuery;

	public function __construct(\Tracer $tracer, \PDO $pdo){
		parent::__construct(
			$tracer,
			$pdo,
			new MessageHistoryBuilder()
		);

		$this->addMessageHistoryQuery = $this->pdo->prepare("
			INSERT INTO `messagesHistory` (
				`time`,
				`source`,
				`user_id`,
				`update_id`,
				`text`,
				`inResponseTo`,
				`statusCode`
			)
			VALUES (
				STR_TO_DATE(:time, '".parent::dateTimeDBFormat."'),
				:source,
				:user_id,
				:update_id,
				:text,
				:inResponseTo,
				:statusCode
			)
		");

	}

	public function addMessageHistory(MessageHistory $messageHistory){
		if($messageHistory->getId() !== null){
			throw new \RuntimeError("Adding a MessageHistory record with existing id");
		}

		$args = array(
			':time'			=> $messageHistory->getTime()->format(parent::dateTimeAppFormat),
			':source'		=> $messageHistory->getSource(),
			':user_id'		=> $messageHistory->getUserId(),
			':update_id'	=> $messageHistory->getUpdateId(),
			':text'			=> $messageHistory->getText(),
			':inResponseTo'	=> $messageHistory->getInResponseTo(),
			':statusCode'	=> $messageHistory->getStatusCode()
		);

		$this->executeInsertUpdateDelete($this->addMessageHistoryQuery, $args, QueryApproach::ONE);
		return $this->getLastInsertId();
	}
}
