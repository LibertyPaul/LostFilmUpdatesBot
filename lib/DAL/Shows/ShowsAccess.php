<?php

namespace DAL;

require_once(__DIR__.'/../CommonAccess.php');
require_once(__DIR__.'/Show.php');


class ShowsAccess extends CommonAccess{
	private $pdo;
	private $getShowByIdQuery;
	private $addShowQuery;

	public function __construct(\PDO $pdo){
		$this->pdo = $pdo;

		$selectFields = "
			SELECT	`shows`.`id`,
					`shows`.`alias`,
					`shows`.`title_ru`,
					`shows`.`title_en`,
					`shows`.`onAir`,
					DATE_FORMAT(`shows`.`firstAppearanceTime`, '".parent::dateTimeDBFormat."') AS firstAppearanceTimeStr,
					DATE_FORMAT(`shows`.`lastAppearanceTime`,  '".parent::dateTimeDBFormat."') AS lastAppearanceTimeStr
			FROM	`shows`
		";

		$this->getShowsByIdQuery = $this->pdo->prepare("
			$selectFields
			WHERE `shows`.`id` = :id
		");

		$this->getShowByAliasQuery = $this->pdo->prepare("
			$selectFields
			WHERE `shows`.`alias` = :alias
		");

		$this->getAll = $this->pdo->prepare("
			$selectFields
			WHERE 1 = 1
		");

		$this->addShowQuery = $this->pdo->prepare("
			INSERT INTO `shows` (
				`alias`,
				`title_ru`,
				`title_en`,
				`onAir`,
				`firstAppearanceTime`,
				`lastAppearanceTime`
			)
			VALUES (
				:alias,
				:title_ru,
				:title_en,
				:onAir,
				STR_TO_DATE(:firstAppearanceTime, '".parent::dateTimeDBFormat."'),
				STR_TO_DATE(:lastAppearanceTime,  '".parent::dateTimeDBFormat."')
			)
		");

		$this->updateShowQuery = $this->pdo->prepare("
			UPDATE `shows`
			SET	`title_ru`				= :title_ru,
				`title_en`				= :title_en,
				`onAir`					= :onAir,
				`lastAppearanceTime`	= STR_TO_DATE(:lastAppearanceTime,  '".parent::dateTimeDBFormat."')
			WHERE `alias` = :alias
		");
	}

	protected function buildObjectFromRow(array $row){
		$show = new Show(
			intval($row['id']),
			$row['alias'],
			$row['title_ru'],
			$row['title_en'],
			$row['onAir'] === 'Y',
			\DateTimeImmutable::createFromFormat(parent::dateTimeAppFormat, $row['firstAppearanceTimeStr']),
			\DateTimeImmutable::createFromFormat(parent::dateTimeAppFormat, $row['lastAppearanceTimeStr'])
		);

		return $show;
	}

	public function getShowById(int $id){
		$args = array(
			':id' => $id
		);

		return $this->executeSearch($this->getShowByIdQuery, $args, QueryApproach::ONE);
	}

	public function getShowByAlias(string $alias){
		$args = array(
			':alias' => $alias
		);

		return $this->executeSearch($this->getShowByAliasQuery, $args, QueryApproach::ONE_IF_EXISTS);
	}

	public function getAliases(){
		$args = array();
		$shows = $this->executeSearch($this->getAll, $args, QueryApproach::MANY);

		$aliases = array();
		
		foreach($shows as $show){
			$aliases[] = $show->getAlias();
		}

		return $aliases;
	}

	public function addShow(Show $show){
		if($show->getId() !== null){
			throw new \RuntimeException("ID is present in a newly submitted show");
		}

		$args = array(
			':alias'				=> $show->getAlias(),
			':title_ru'				=> $show->getTitleRu(),
			':title_en'				=> $show->getTitleEn(),
			':onAir'				=> $show->isOnAir() ? 'Y' : 'N',
			':firstAppearanceTime'	=> $show->getFirstAppearanceTime()->format(parent::dateTimeAppFormat),
			':lastAppearanceTime'	=> $show->getLastAppearanceTime()->format(parent::dateTimeAppFormat)
		);

		$this->executeInsertUpdateDelete($this->addShowQuery, $args, QueryApproach::ONE);

		$show->setId(intval($this->pdo->lastInsertId()));
	}

	public function updateShow(Show $show){
		$args = array(
			':title_ru'				=> $show->getTitleRu(),
			':title_en'				=> $show->getTitleEn(),
			':onAir'				=> $show->isOnAir() ? 'Y' : 'N',
			':lastAppearanceTime'	=> $show->getLastAppearanceTime()->format(parent::dateTimeAppFormat),
			':alias'				=> $show->getAlias()
		);

		$this->executeInsertUpdateDelete($this->updateShowQuery, $args, QueryApproach::ONE);
	}

	public function lockShowsWrite(){
		$this->pdo->query('LOCK TABLES `shows` WRITE');
	}

	public function unlockTables(){
		$this->pdo->query('UNLOCK TABLES');
	}
}
