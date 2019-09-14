<?php

namespace CommandSubstitutor;

require_once(__DIR__.'/CoreCommand.php');
require_once(__DIR__.'/APICommand.php');

class CommandSubstitutor{	
	private $APIToCoreQuery;
	private $CoreToAPIQuery;

	public function __construct(\PDO $pdo){
		$this->APIToCoreQuery = $pdo->prepare('
			SELECT `id`, `text`
			FROM `coreCommands`
			WHERE `id` = (
				SELECT `coreCommandId`
				FROM `APICommands`
				WHERE `API` = :API
				AND `text` = :text
			)
		');

		$this->CoreToAPIQuery = $pdo->prepare('
			SELECT `id`, `API`, `text`, `coreCommandId`
			FROM `APICommands`
			WHERE `API` = :API
			AND `coreCommandId` = (
				SELECT `id`
				FROM `coreCommands`
				WHERE `text` = :text
			)
			ORDER BY `priority`
		');

		$this->CoreByIdQuery = $pdo->prepare('
			SELECT `id`, `text`
			FROM `coreCommands`
			WHERE `id` = :id
		');

		$this->CoreToAPIMappingQuery = $pdo->prepare("
			SELECT cc.`text` AS coreCommandText, ac.`APICommands` AS APICommandsText
			FROM `coreCommands` cc
			JOIN (
				SELECT
					`coreCommandId`,
					GROUP_CONCAT(
						`text`
						ORDER BY `priority`
						SEPARATOR ' | '
					) AS APICommands
				FROM `APICommands`
				WHERE `API` = :API
				GROUP BY `coreCommandId`
			) ac ON cc.`id` = ac.`coreCommandId`
		");

		$this->getAllCoreCommands = $pdo->prepare('
			SELECT `id`, `text` FROM `coreCommands`
		');
	}

	public function convertAPIToCore($API, $text){
		$this->APIToCoreQuery->execute(
			array(
				':API' => $API,
				':text' => $text
			)
		);

		$row = $this->APIToCoreQuery->fetch();
		if($row === false){
			return null;
		}

		return new CoreCommand(intval($row['id']), strval($row['text']));
	}

	public function convertCoreToAPI($API, $text){
		$this->CoreToAPIQuery->execute(
			array(
				':API' => $API,
				':text' => $text
			)
		);

		$result = array();
		while($row = $this->CoreToAPIQuery->fetch()){
			$result[] = new APICommand(
				$row['id'],
				$row['API'],
				$row['text'],
				$row['coreCommandId']
			);
		}

		return $result;
	}

	public function getCoreCommand($id){
		$this->CoreByIdQuery->execute(
			array(
				':id' => $id
			)
		);

		$row = $this->CoreByIdQuery->fetch();
		if($row === false){
			return null;
		}

		return new CoreCommand(intval($row['id']), strval($row['text']));
	}

	public function getCoreCommandsAssociative(){
		$this->getAllCoreCommands->execute();

		$coreCommandsAssociative = array();

		while($row = $this->getAllCoreCommands->fetch()){
			$id = intval($row['id']);
			$text = $row['text'];

			$coreCommandsAssociative[$id] = $text;
		}

		return $coreCommandsAssociative;
	}

	public function replaceCoreCommandsInText($API, $text){
		$this->CoreToAPIMappingQuery->execute(
			array(
				':API' => $API
			)
		);

		while($row = $this->CoreToAPIMappingQuery->fetch()){
			$text = str_replace(
				$row['coreCommandText'],
				$row['APICommandsText'],
				$text
			);
		}

		return $text;
	}
}


		
