<?php

namespace CommandSubstitutor;

require_once(__DIR__.'/CoreCommand.php');
require_once(__DIR__.'/APICommand.php');
require_once(__DIR__.'/../QueryTraits/Approach.php');

class CommandSubstitutor{	
	const API_NAME_KEY = 'API';
	const CORE_COMMAND_ID_KEY = 'CoreCommandID';
	const CORE_COMMAND_TEXT_KEY = 'CoreCommandText';
	const API_COMMAND_ID_KEY = 'APICommandID';
	const API_COMMAND_TEXT_KEY = 'APICommandText';

	private $coreCommands;
	private $mapping;

	public function __construct(\PDO $pdo){
		$this->reloadCache($pdo);
	}

	private function reloadCache(\PDO $pdo){
		$loadCoreCommandsQuery = $pdo->prepare('
			SELECT
				`id`	AS '.self::CORE_COMMAND_ID_KEY.',
				`text`	AS '.self::CORE_COMMAND_TEXT_KEY.'
			FROM `coreCommands`
		');

		$loadMappingQuery = $pdo->prepare('
			SELECT
				ac.`API`	AS '.self::API_NAME_KEY.',
				cc.`id`		AS '.self::CORE_COMMAND_ID_KEY.',
				cc.`text`	AS '.self::CORE_COMMAND_TEXT_KEY.',
				ac.`id`		AS '.self::API_COMMAND_ID_KEY.',
				ac.`text`	AS '.self::API_COMMAND_TEXT_KEY.'
			FROM `APICommands` ac
			JOIN `coreCommands` cc ON ac.`coreCommandId` = cc.`id`
		');

		$loadCoreCommandsQuery->execute();
		$coreCommands = $loadCoreCommandsQuery->fetchAll(\PDO::FETCH_ASSOC);

		foreach($coreCommands as $key => $command){
			$codeCommandId = $coreCommands[$key][self::CORE_COMMAND_ID_KEY];
			$codeCommandId = intval($codeCommandId);
			$coreCommands[$key][self::CORE_COMMAND_ID_KEY] = $codeCommandId;
		}

		$loadMappingQuery->execute();

		$rows = $loadMappingQuery->fetchAll(\PDO::FETCH_ASSOC);

		$APIs = array_unique(array_column($rows, self::API_NAME_KEY));
		$newMapping = array_fill_keys($APIs, array());

		foreach($rows as $row){
			$APIName = $row[self::API_NAME_KEY];

			$newMapping[$APIName][] = array(
				self::CORE_COMMAND_ID_KEY	=> intval($row[self::CORE_COMMAND_ID_KEY]),
				self::CORE_COMMAND_TEXT_KEY	=> $row[self::CORE_COMMAND_TEXT_KEY],
				self::API_COMMAND_ID_KEY	=> intval($row[self::API_COMMAND_ID_KEY]),
				self::API_COMMAND_TEXT_KEY	=> $row[self::API_COMMAND_TEXT_KEY]
			);
		}

		$this->coreCommands = $coreCommands;
		$this->mapping = $newMapping;
	}

	private function coreLookup(
		\QueryTraits\Approach $approach,
		int $coreCommandID = null,
		string $coreCommandText = null
	){
		$res = array();

		foreach($this->coreCommands as $row){
			$match = (
				(
					$coreCommandID === null ||
					$coreCommandID === $row[self::CORE_COMMAND_ID_KEY]
				) &&
				(
					$coreCommandText === null ||
					$coreCommandText === $row[self::CORE_COMMAND_TEXT_KEY]
				)
			);

			if($match){
				$res[] = $row;
			}
		}

		$approach->verify(count($res));
		return $approach->repack($res);
	}

	private function mappingLookup(
		\QueryTraits\Approach $approach,
		string $API,
		int $coreCommandID = null,
		string $coreCommandText = null,
		int $APICommandID = null,
		string $APICommandText = null
	){
		$res = array();

		foreach($this->mapping[$API] as $row){
			$match = (
				(
					$coreCommandID === null ||
					$coreCommandID === $row[self::CORE_COMMAND_ID_KEY]
				) &&
				(
					$coreCommandText === null ||
					$coreCommandText === $row[self::CORE_COMMAND_TEXT_KEY]
				) &&
				(
					$APICommandID === null ||
					$APICommandID === $row[self::API_COMMAND_ID_KEY]
				) &&
				(
					$APICommandText === null ||
					$APICommandText === $row[self::API_COMMAND_TEXT_KEY]
				)
			);

			if($match){
				$res[] = $row;
			}
		}

		$approach->verify(count($res));
		return $approach->repack($res);
	}

	public function convertAPIToCore(string $API, string $text){
		$res = $this->mappingLookup(
			\QueryTraits\Approach::OneIfExists(),
			$API,
			null,
			null,
			null,
			$text
		);

		if($res === null){
			return null;
		}

		return new CoreCommand(
			$res[self::CORE_COMMAND_ID_KEY],
			$res[self::CORE_COMMAND_TEXT_KEY]
		);
	}


	public function convertCoreToAPI(string $API, string $text){
		$res = $this->mappingLookup(
			\QueryTraits\Approach::One(),
			$API,
			null,
			$text
		);

		return new APICommand(
			$res[self::API_COMMAND_ID_KEY],
			$res[self::API_NAME_KEY],
			$res[self::API_COMMAND_TEXT_KEY],
			$res[self::CORE_COMMAND_ID_KEY]
		);
	}

	public function getCoreCommand(int $id){
		$res = $this->coreLookup(
			\QueryTraits\Approach::One(),
			$id
		);

		return new CoreCommand(
			$res[self::CORE_COMMAND_ID_KEY],
			$res[self::CORE_COMMAND_TEXT_KEY]
		);
	}

	public function getCoreCommandsAssociative(){
		$coreCommands = $this->coreLookup(\QueryTraits\Approach::Many());
		$coreCommandsAssociative = array();

		foreach($coreCommands as $row){
			$id = $row[self::CORE_COMMAND_ID_KEY];
			$text = $row[self::CORE_COMMAND_TEXT_KEY];

			$coreCommandsAssociative[$id] = $text;
		}

		return $coreCommandsAssociative;
	}

	public function replaceCoreCommands(
		string $API,
		/* string or array */ $haystack,
		string $format = "%s"
	){
		if($haystack === null){
			return null;
		}

		if(is_array($haystack) === false && is_string($haystack) === false){
			throw new \LogicException("Invalid haystack type: ".gettype($haystack));
		}

		$APIMapping = $this->mappingLookup(
			\QueryTraits\Approach::Many(),
			$API
		);

		$from	= array_column($APIMapping, self::CORE_COMMAND_TEXT_KEY);
		$to		= array_column($APIMapping, self::API_COMMAND_TEXT_KEY);

		foreach($to as $key => $value){
			$to[$key] = sprintf($format, $to[$key]);
		}

		return str_replace($from, $to, $haystack);
	}
}


		
