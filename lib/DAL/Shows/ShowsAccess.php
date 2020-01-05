<?php

namespace DAL;

require_once(__DIR__.'/../CommonAccess.php');
require_once(__DIR__.'/Show.php');
require_once(__DIR__.'/MatchedShow.php');

abstract class ShowAction{
	const MIN = 1;

	const Add = 1;
	const Remove = 2;
	const AddTentative = 3;

	const MAX = 3;

	public static function verify(int $action){
		assert($action >= self::MIN);
		assert($action <= self::MAX);
	}
}

class ShowsAccess extends CommonAccess{
	private $pdo;
	private $getShowByIdQuery;
	private $getShowByAliasQuery;
	private $getUserShowsQuery;
	private $getEligibleShowsQuery;
	private $getEligibleShowByTitleQuery;
	private $getEligibleShowsWithScoreQuery;
	private $getAllQuery;
	private $addShowQuery;
	private $updateShowQuery;

	public function __construct(\PDO $pdo){
		$this->pdo = $pdo;

		$selectFields = "
			SELECT
				`shows`.`id`,
				`shows`.`alias`,
				`shows`.`title_ru`,
				`shows`.`title_en`,
				`shows`.`onAir`,
				DATE_FORMAT(`shows`.`firstAppearanceTime`, '".parent::dateTimeDBFormat."') AS firstAppearanceTimeStr,
				DATE_FORMAT(`shows`.`lastAppearanceTime`,  '".parent::dateTimeDBFormat."') AS lastAppearanceTimeStr
		";

		$this->getShowsByIdQuery = $this->pdo->prepare("
			$selectFields
			FROM `shows`
			WHERE `shows`.`id` = :id
		");

		$this->getShowByAliasQuery = $this->pdo->prepare("
			$selectFields
			FROM `shows`
			WHERE `shows`.`alias` = :alias
		");

		$this->getUserShowsQuery = $this->pdo->prepare("
			$selectFields
			FROM `shows`
			WHERE `shows`.`id` IN (
				SELECT `tracks`.`show_id`
				FROM `tracks`
				WHERE `tracks`.`user_id` = :user_id
			)
			ORDER BY `shows`.`title_ru`
		");

		$this->getEligibleShowsQuery = $this->pdo->prepare("
			$selectFields
			FROM `shows`
			WHERE (
				`id` IN(
					SELECT `show_id`
					FROM `tracks`
					WHERE `user_id` = :user_id
				)
				XOR :showAction
			)
			AND ((`shows`.`onAir` = 'Y') OR NOT :showAction)
			ORDER BY `title_ru`, `title_en`
		");

		$this->getEligibleShowByTitleQuery = $this->pdo->prepare("
			$selectFields
			FROM `shows`
			WHERE (
				`id` IN(
					SELECT `show_id`
					FROM `tracks`
					WHERE `user_id` = :user_id
				)
				XOR :showAction
			)
			AND ((`shows`.`onAir` = 'Y') OR NOT :showAction)
			AND CONCAT(`title_ru`, '(', `title_en`, ')') = :title
			ORDER BY `title_ru`, `title_en`

		");

		$this->getEligibleShowsWithScoreQuery = $this->pdo->prepare("
			$selectFields,
			MATCH(`title_ru`, `title_en`) AGAINST(:show_name) AS `score`
			FROM `shows`
			WHERE (
				`id` IN (
					SELECT `show_id`
					FROM `tracks`
					WHERE `user_id` = :user_id
				)
				XOR :showAction
			)
			AND ((`shows`.`onAir` = 'Y') OR NOT :showAction)
			HAVING `score` > 0.1
			ORDER BY `score` DESC
		");

		$this->getAllQuery = $this->pdo->prepare("
			$selectFields
			FROM `shows`
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
		if(array_key_exists('score', $row)){
			$show = new MatchedShow(
				intval($row['id']),
				$row['alias'],
				$row['title_ru'],
				$row['title_en'],
				$row['onAir'] === 'Y',
				\DateTimeImmutable::createFromFormat(parent::dateTimeAppFormat, $row['firstAppearanceTimeStr']),
				\DateTimeImmutable::createFromFormat(parent::dateTimeAppFormat, $row['lastAppearanceTimeStr']),
				doubleval($row['score'])
			);
		}
		else{
			$show = new Show(
				intval($row['id']),
				$row['alias'],
				$row['title_ru'],
				$row['title_en'],
				$row['onAir'] === 'Y',
				\DateTimeImmutable::createFromFormat(parent::dateTimeAppFormat, $row['firstAppearanceTimeStr']),
				\DateTimeImmutable::createFromFormat(parent::dateTimeAppFormat, $row['lastAppearanceTimeStr'])
			);
		}

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

	public function getUserShows(int $user_id){
		$args = array(
			':user_id' => $user_id
		);

		return $this->executeSearch($this->getUserShowsQuery, $args, QueryApproach::MANY);
	}

	public function getEligibleShows(int $user_id, int $action){
		ShowAction::verify($action);
		
		$args = array(
			':user_id'		=> $user_id,
			':showAction'	=> $action !== ShowAction::Remove
		);

		return $this->executeSearch($this->getEligibleShowsQuery, $args, QueryApproach::MANY);
	}

	public function getEligibleShowByTitle(int $user_id, string $title, int $action){
		ShowAction::verify($action);
		
		$args = array(
			':user_id'		=> $user_id,
			':title'		=> $title,
			':showAction'	=> $action !== ShowAction::Remove
		);

		return $this->executeSearch($this->getEligibleShowByTitleQuery, $args, QueryApproach::ONE_IF_EXISTS);
	}

	public function getEligibleShowsWithScore(int $suer_id, string $title, int $action){
		ShowAction::verify($action);

		$args = array(
			':user_id'		=> $user_id,
			':title'		=> $title,
			':showAction'	=> $action !== ShowAction::Remove
		);

		return $this->executeSearch($this->getEligibleShowsWithScoreQuery, $args, QueryApproach::MANY);
	}

	public function getAliases(){
		$args = array();
		$shows = $this->executeSearch($this->getAllQuery, $args, QueryApproach::MANY);

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
