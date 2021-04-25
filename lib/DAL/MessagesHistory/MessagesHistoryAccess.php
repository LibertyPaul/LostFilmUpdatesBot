<?php

namespace DAL;

require_once(__DIR__.'/../CommonAccess.php');
require_once(__DIR__.'/MessageHistoryBuilder.php');
require_once(__DIR__.'/MessageHistory.php');

class MessagesHistoryDuplicateExternalIdException extends \RuntimeException {}

class MessagesHistoryAccess extends CommonAccess{
	private $addMessageHistoryQuery;
    private $getByUpdateIdQuery;

    public function __construct(\PDO $pdo){
		parent::__construct(
			$pdo,
			new MessageHistoryBuilder()
		);

		$selectFields = "
			SELECT
				`id`,
				DATE_FORMAT(`time`, '".parent::dateTimeDBFormat."') AS timeStr,
				`source`,
				`user_id`,
				`external_id`,
				`text`,
				`inResponseTo`,
				`statusCode`
		";

		$this->getByUpdateIdQuery = $this->pdo->prepare("
			$selectFields
			FROM `messagesHistory`
			WHERE `external_id` = :external_id 
		");

		$this->addMessageHistoryQuery = $this->pdo->prepare("
			INSERT INTO `messagesHistory` (
				`time`,
				`source`,
				`user_id`,
				`external_id`,
				`text`,
				`inResponseTo`,
				`statusCode`
			)
			VALUES (
				STR_TO_DATE(:time, '".parent::dateTimeDBFormat."'),
				:source,
				:user_id,
				:external_id,
				:text,
				:inResponseTo,
				:statusCode
			)
		");
	}

	public function getByUpdateId(int $updateId){
		$args = array(
			':external_id' => $updateId
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
			throw new \LogicException("Adding a MessageHistory record with existing id");
		}

		$args = array(
			':time'			=> $messageHistory->getTime()->format(parent::dateTimeAppFormat),
			':source'		=> $messageHistory->getSource(),
			':user_id'		=> $messageHistory->getUserId(),
			':external_id'	=> $messageHistory->getExternalId(),
			':text'			=> $messageHistory->getText(),
			':inResponseTo'	=> $messageHistory->getInResponseTo(),
			':statusCode'	=> $messageHistory->getStatusCode()
		);

		try{	
			return $this->execute(
				$this->addMessageHistoryQuery,
				$args,
				\QueryTraits\Type::Write(),
				\QueryTraits\Approach::One()
			);
		}
		catch(DuplicateValueException $ex){
			switch($ex->getConstrainingIndexName()){
			case 'external_id':
				throw new MessagesHistoryDuplicateExternalIdException("Constraint violation", 0, $ex);

			default:
				throw $ex;
			}
		}
	}
}
