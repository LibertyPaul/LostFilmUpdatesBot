<?php

namespace DAL;

require_once(__DIR__.'/../CommonAccess.php');
require_once(__DIR__.'/MessageHistory.php');


class MassagesHistoryAccess extends CommonAccess{
	private $addMessageHistoryQuery;

	public function __construct(\PDO $pdo){
		parent::__construct($pdo);

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
				'User',
				:user_id,
				:update_id,
				:text,
				:inResponseTo,
				:statusCode
			)
		");

	}

	# Note: the method is not used, persists only in order to comply with CommonAccess requirements.
	protected function buildObjectFromRow(array $row){
		$messageHistory = new MessageHistory(
			intval($row['id']),
			\DateTimeImmutable::createFromFormat(parent::dateTimeAppFormat, $row['createdStr']),
			$row['source'],
			intval($row['user_id']),
			intval($row['update_id']),
			$row['text'],
			intval($row['inResponseTo']),
			intval($row['statusCode'])
		);

		return $messageHistory;
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
