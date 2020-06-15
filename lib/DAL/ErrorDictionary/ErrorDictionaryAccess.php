<?php

namespace DAL;

require_once(__DIR__.'/../CommonAccess.php');
require_once(__DIR__.'/ErrorDictionaryRecordBuilder.php');

class ErrorDictionaryAccess extends CommonAccess{
	private $getErrorDictionaryRecordByIdQuery;
	private $getErrorDictionaryRecordByErrorCredentialsWithLockQuery;
	private $addErrorDictionaryRecordQuery;

	public function __construct(\PDO $pdo){
		parent::__construct(
			$pdo,
			new ErrorDictionaryRecordBuilder()
		);

		$selectFields = "
			SELECT
				`ErrorDictionary`.`id`,
				`ErrorDictionary`.`level`,
				`ErrorDictionary`.`source`,
				`ErrorDictionary`.`line`,
				`ErrorDictionary`.`fullText`
		";

		$this->getErrorDictionaryRecordByIdQuery = $this->pdo->prepare("
			$selectFields
			FROM	`ErrorDictionary`
			WHERE	`ErrorDictionary`.`id` = :id
		");

		$this->getErrorDictionaryRecordByErrorCredentialsWithLockQuery = $this->pdo->prepare("
			$selectFields
			FROM	`ErrorDictionary`
			WHERE 	`ErrorDictionary`.`level`	= :level
			AND		`ErrorDictionary`.`source`	= :source
			AND		`ErrorDictionary`.`line`	= :line
			AND		`ErrorDictionary`.`text`	= :text
			FOR UPDATE
		");

		$this->addErrorDictionaryRecordQuery = $this->pdo->prepare("
			INSERT INTO `ErrorDictionary` (
				`level`,
				`source`,
				`line`,
				`text`,
				`fullText`
			)
			VALUES (
				:level,
				:source,
				:line,
				:text,
				:fullText
			)
		");
	}

	public function getErrorDictionaryRecordById(int $id){
		$args = array(
			':id' => $id
		);

		return $this->execute(
			$this->getErrorDictionaryRecordByIdQuery,
			$args,
			\QueryTraits\Type::Read(),
			\QueryTraits\Approach::One()
		);
	}

	public function createOrGetErrorRecordId(ErrorDictionaryRecord $record){
		if($record->getId() !== null){
			throw new \LogicException("Id is already set for the record ($record->id).");
		}

		$args = array(
			':level'	=> $record->getLevel(),
			':source'	=> $record->getSource(),
			':line'		=> $record->getLine(),
			':text'		=> substr($record->getText(), 0, 500)
		);

		$this->startTransaction();

		try{
			$res = $this->execute(
				$this->getErrorDictionaryRecordByErrorCredentialsWithLockQuery,
				$args,
				\QueryTraits\Type::Read(),
				\QueryTraits\Approach::OneIfExists()
			);

			if($res !== null){
				$this->commit();
				return $res->getId();
			}

			$args[':fullText'] = $record->getText();

			$this->execute(
				$this->addErrorDictionaryRecordQuery,
				$args,
				\QueryTraits\Type::Write(),
				\QueryTraits\Approach::One()
			);
		}
		catch(\Throwable $ex){
			$this->rollback();
			throw $ex;
		}

		$this->commit();
		return $this->getLastInsertId();
	}
}
