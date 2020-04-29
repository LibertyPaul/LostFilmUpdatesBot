<?php

namespace DAL;

require_once(__DIR__.'/DAOBuilderInterface.php');

abstract class QueryApproach{
	const MIN			= 1;

	const ONE_IF_EXISTS	= 1;
	const ONE			= 2;
	const MANY			= 3;

	const MAX			= 3;

	static public function validate(int $approach){
		assert($approach >= self::MIN);
		assert($approach <= self::MAX);
	}
}

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

	protected function executeSearch(\PDOStatement $query, array $args, int $approach){
		QueryApproach::validate($approach);

		try{
			$query->execute($args);
		}
		catch(\PDOException $ex){
			$this->tracer->logException(
				'[o]', __FILE__, __LINE__, $ex
			);

			$this->tracer->logDebug(
				'[o]', __FILE__, __LINE__, PHP_EOL.print_r($args, true)
			);

			throw new \RuntimeException("Failed to execute query");
		}

		$rows = $query->fetchAll();

		switch($approach){
			case QueryApproach::ONE:
				switch(count($rows)){
					case 0:
						throw new \LogicException("The record was not found under condition".PHP_EOL.print_r($args, true));

					case 1:
						return $this->DAOBuilder->buildObjectFromRow($rows[0], self::dateTimeAppFormat);

					default:
						throw new \LogicException(
							"Multiple records were found while one was expected.".PHP_EOL.print_r($args, true)
						);
				}
				break;

			case QueryApproach::ONE_IF_EXISTS:
				switch(count($rows)){
					case 0:
						return null;

					case 1:
						return $this->DAOBuilder->buildObjectFromRow($rows[0], self::dateTimeAppFormat);

					default:
						throw new \LogicException(
							"Multiple records were found while one was expected.".PHP_EOL.print_r($args, true)
						);
				}
				break;

			case QueryApproach::MANY:
				$objects = array();

				foreach($rows as $row){
					$objects[] = $this->DAOBuilder->buildObjectFromRow($row, self::dateTimeAppFormat);
				}

				return $objects;

			default:
				throw new \LogicException("Invalid Select Approach: [$approach].");
		}
	}

	protected function executeInsertUpdateDelete(\PDOStatement $query, array $args, int $approach){
		QueryApproach::validate($approach);

		try{
			$query->execute($args);
		}
		catch(\PDOException $ex){
			$this->tracer->logException(
				'[o]', __FILE__, __LINE__, $ex
			);

			$this->tracer->logDebug(
				'[o]', __FILE__, __LINE__, PHP_EOL.print_r($args, true)
			);

			throw new \RuntimeException("Failed to execute query");
		}

		$rowsAffected = $query->rowCount();

		# TODO: Get rid of (almost) duplicate code
		switch($approach){
			case QueryApproach::ONE:
				switch($rowsAffected){
					case 0:
						throw new \LogicException("Record was not found under condition".PHP_EOL.print_r($args, true));

					case 1:
						return null;

					default:
						throw new \LogicException(
							"Multiple records were affected while one was expected.".PHP_EOL.print_r($args, true)
						);
				}
				break;

			case QueryApproach::ONE_IF_EXISTS:
				switch(count($rows)){
					case 0:
						return null;

					case 1:
						return null;

					default:
						throw new \LogicException(
							"Multiple records were affected while one was expected.".PHP_EOL.print_r($args, true)
						);
				}
				break;

			case QueryApproach::MANY:
				return null;

			default:
				throw new \LogicException("Invalid Select Approach: [$approach].");
		}
	}

	protected function getLastInsertId(){
		$id = intval($this->pdo->lastInsertId());
		if($this->pdo->errorCode() === 'IM001'){
			throw new \RuntimeException("PDO driver does not support lastInsertId() method.");
		}
		
		return intval($id);
	}
}
