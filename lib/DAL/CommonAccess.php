<?php

namespace DAL;

require_once(__DIR__.'/DAOBuilderInterface.php');
require_once(__DIR__.'/../QueryTraits/Type.php');
require_once(__DIR__.'/../QueryTraits/Approach.php');


class ConstraintViolationException extends \RuntimeException{};
class DuplicateValueException extends ConstraintViolationException{
	public function getConstrainingIndexName(): string{
		return $this->getMessage();
	}
};

abstract class CommonAccess{
	const dateTimeDBFormat = '%d.%m.%Y %H:%i:%S.%f';
	const dateTimeAppFormat = 'd.m.Y H:i:s.u';

	protected $tracer;
	protected $pdo;
	protected $DAOBuilder;

	public function __construct(\Tracer $tracer, \PDO $pdo, DAOBuilderInterface $DAOBuilder){
		$this->tracer = $tracer;
		$this->pdo = $pdo;
		$this->DAOBuilder = $DAOBuilder;
	}

	private function get23000IndexName(string $errorMessage){
		$regexp = "/Integrity constraint violation: 1062 Duplicate entry '\w+' for key '(\w+)'/";
		$matches = array();
		
		$matchesRes = preg_match($regexp, $errorMessage, $matches);
		if($matchesRes === false){
			$this->tracer->logfError(
				'[o]', __FILE__, __LINE__,
				'preg_match has failed with code: [%s]'.PHP_EOL.
				'Message: [%s]',
				preg_last_error(),
				$errorMessage
			);

			return null;
		}

		if($matchesRes === 0){
			$this->tracer->logfError(
				'[o]', __FILE__, __LINE__,
				'23000 error text does not match the pattern'.PHP_EOL.
				'Message: [%s]',
				$errorMessage
			);

			return null;
		}

		assert($matchesRes === 1);

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
				$indexName = $this->get23000IndexName($ex->getMessage());
				throw new DuplicateValueException($indexName, 0, $ex);

			default:
				$this->tracer->logDebug(
					'[o]', __FILE__, __LINE__, PHP_EOL.print_r($args, true)
				);

				$this->tracer->logException(
					'[o]', __FILE__, __LINE__, $ex
				);

				throw new \RuntimeException("Failed to execute query.", 0, $ex);
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
			return null;
		}

		$rows = $query->fetchAll();
		$result = array();
		
		foreach($rows as $row){
			$result[] = $this->DAOBuilder->buildObjectFromRow($row, self::dateTimeAppFormat);
		}

		return $approach->repack($result);
	}

	protected function getLastInsertId(){
		$id = intval($this->pdo->lastInsertId());
		if($this->pdo->errorCode() === 'IM001'){
			throw new \RuntimeException("PDO driver does not support lastInsertId() method.");
		}
		
		return $id;
	}
}
