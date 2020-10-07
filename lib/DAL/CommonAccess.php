<?php

namespace DAL;

require_once(__DIR__.'/DAOBuilderInterface.php');
require_once(__DIR__.'/../QueryTraits/Type.php');
require_once(__DIR__.'/../QueryTraits/Approach.php');


class ConstraintViolationException extends \RuntimeException{
	public function getConstrainingIndexName(): string{
		return $this->getMessage();
	}
}

class DuplicateValueException extends ConstraintViolationException{};
class ForeignKeyViolation extends ConstraintViolationException{};


abstract class CommonAccess{
	const dateTimeDBFormat = '%d.%m.%Y %H:%i:%S.%f';
	const dateTimeAppFormat = 'd.m.Y H:i:s.u';

	protected $pdo;
	protected $DAOBuilder;

	public function __construct(\PDO $pdo, DAOBuilderInterface $DAOBuilder){
		$this->pdo = $pdo;
		$this->DAOBuilder = $DAOBuilder;
	}

	private function get23000IndexName(string $errorMessage){
		$regexps = array(
			"/Integrity constraint violation: 1062 Duplicate entry '\w+' for key '(\w+)'/",
			"/Integrity constraint violation: 1452 .*? FOREIGN KEY \(`(\w+)`\)/"
			# TODO: Map to correct exception type
		);

		$matches = array();

		foreach($regexps as $regexp){
			$matchesRes = preg_match($regexp, $errorMessage, $matches);
			if($matchesRes !== false && $matchesRes > 0){
				break;
			}
		}

		if($matchesRes === false){
			throw new \LogicException(
				sprintf(
					"preg_match has failed with code: [%s]".PHP_EOL.
					'Message: [%s]',
					preg_last_error(),
					$errorMessage
				)
			);
		}

		if($matchesRes === 0){
			throw new \LogicException(
				sprintf(
					'23000 error text does not match any pattern'.PHP_EOL.
					'Message: [%s]',
					$errorMessage
				)
			);
		}

		return $matches[1];
	}

	protected function execute(
		\PDOStatement $query,
		array $args,
		\QueryTraits\Type $type,
		\QueryTraits\Approach $approach
	){
		try{
			$query->execute($args);
		}
		catch(\PDOException $ex){
			switch($ex->getCode()){
			case 23000:
				$message = $ex->getMessage();
				try{
					$indexName = $this->get23000IndexName($message);
				}
				catch(\Throwable $ex){
					$indexName = "<unidentified: $message>";
				}

				throw new DuplicateValueException($indexName, 0, $ex);

			default:
				throw $ex;
			}
		}

		try{
			$approach->verify($query->rowCount());
		}
		catch(\QueryTraits\ApproachMismatchException $ex){
			throw new \LogicException(
				"Query Approach verification failed.".PHP_EOL.print_r($args, true),
				0,
				$ex
			);
		}

		if($type->getType() === \QueryTraits\Type::WRITE){
			if($approach->getApproach() === \QueryTraits\Approach::ONE){
				return $this->getLastInsertId();
			}
			else{
				return null;
			}
		}

		$rows = $query->fetchAll();
		$result = array();
		
		foreach($rows as $row){
			$result[] = $this->DAOBuilder->buildObjectFromRow($row, self::dateTimeAppFormat);
		}

		return $approach->repack($result);
	}

	private function getLastInsertId(){
		$id = intval($this->pdo->lastInsertId());
		if($this->pdo->errorCode() === 'IM001'){
			throw new \RuntimeException("PDO driver does not support lastInsertId() method.");
		}
		
		return $id;
	}

	protected function startTransaction(){
		$this->pdo->beginTransaction();
	}

	protected function commit(){
		$this->pdo->commit();
	}

	protected function rollback(){
		$this->pdo->rollBack();
	}
}
