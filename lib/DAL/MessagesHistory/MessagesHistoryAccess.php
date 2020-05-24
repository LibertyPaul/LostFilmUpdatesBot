<?php

namespace DAL;

require_once(__DIR__.'/../CommonAccess.php');
require_once(__DIR__.'/MessageHistoryBuilder.php');
require_once(__DIR__.'/MessageHistory.php');

class MessagesHistoryDuplicateUpdateIdException extends \RuntimeException{};

class MessagesHistoryAccess extends CommonAccess{
	private $addMessageHistoryQuery;

	public function __construct(\Tracer $tracer, \PDO $pdo){
		parent::__construct(
			$tracer,
			$pdo,
			new MessageHistoryBuilder()
		);

		$selectFields = "
			SELECT
				`id`,
				DATE_FORMAT(`time`, '".parent::dateTimeDBFormat."') AS timeStr,
				`source`,
				`user_id`,
				`update_id`,
				`text`,
				`inResponseTo`,
				`statusCode`
		";

		$this->getByUpdateIdQuery = $this->pdo->prepare("
			$selectFields
			FROM `messagesHistory`
			WHERE `update_id` = :update_id 
		");

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

	public function getByUpdateId(int $updateId){
		$args = array(
			':update_id' => $updateId
		);

		$message = $this->execute(
			$this->getByUpdateIdQuery,
			$args,
			\QueryTraits\Type::Read(),
			\QueryTraits\Approach::One()
		);

		return $message;
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

		try{	
			$this->execute(
				$this->addMessageHistoryQuery,
				$args,
				\QueryTraits\Type::Write(),
				\QueryTraits\Approach::One()
			);
		}
		catch(DuplicateValueException $ex){
			switch($ex->whichColumn()){
			case 'update_id':
				throw new MessagesHistoryDuplicateUpdateIdException("", 0, $ex);

			default:
				throw $ex;
			}
		}

		return $this->getLastInsertId();
	}
}
