<?php

namespace DAL;

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

	protected $pdo;

	public function __construct(\PDO $pdo){
		$this->pdo = $pdo;
	}

	abstract protected function buildObjectFromRow(array $row);

	protected function executeSearch(\PDOStatement $query, array $args, int $approach){
		QueryApproach::validate($approach);

		$query->execute($args);
		$rows = $query->fetchAll();

		switch($approach){
			case QueryApproach::ONE:
				switch(count($rows)){
					case 0:
						throw new \RuntimeException("The record was not found under condition".PHP_EOL.print_r($args, true));

					case 1:
						return $this->buildObjectFromRow($rows[0]);

					default:
						throw new \RuntimeException(
							"Multiple records were found while one was expected.".PHP_EOL.print_r($args, true)
						);
				}
				break;

			case QueryApproach::ONE_IF_EXISTS:
				switch(count($rows)){
					case 0:
						return null;

					case 1:
						return $this->buildObjectFromRow($rows[0]);

					default:
						throw new \RuntimeException(
							"Multiple records were found while one was expected.".PHP_EOL.print_r($args, true)
						);
				}
				break;

			case QueryApproach::MANY:
				$objects = array();

				foreach($rows as $row){
					$objects[] = $this->buildObjectFromRow($row);
				}

				return $objects;

			default:
				throw new \RuntimeException("Invalid Select Approach: [$approach].");
		}
	}

	protected function executeInsertUpdateDelete(\PDOStatement $query, array $args, int $approach){
		QueryApproach::validate($approach);

		$query->execute($args);
		$rowsAffected = $query->rowCount();

		# TODO: Get rid of (almost) duplicate code
		switch($approach){
			case QueryApproach::ONE:
				switch($rowsAffected){
					case 0:
						throw new \RuntimeException("Show was not found under condition".PHP_EOL.print_r($args, true));

					case 1:
						return null;

					default:
						throw new \RuntimeException(
							"Multiple records were found while one was expected.".PHP_EOL.print_r($args, true)
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
						throw new \RuntimeException(
							"Multiple records were found while one was expected.".PHP_EOL.print_r($args, true)
						);
				}
				break;

			case QueryApproach::MANY:
				return null;

			default:
				throw new \RuntimeException("Invalid Select Approach: [$approach].");
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
