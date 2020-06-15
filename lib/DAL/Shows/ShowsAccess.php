<?php

namespace DAL;

require_once(__DIR__.'/../CommonAccess.php');
require_once(__DIR__.'/Show.php');
require_once(__DIR__.'/MatchedShow.php');
require_once(__DIR__.'/ShowBuilder.php');

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
		parent::__construct(
			$pdo,
			new ShowBuilder()
		);

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

		$this->getShowByIdQuery = $this->pdo->prepare("
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
			AND CONCAT(`title_ru`, ' (', `title_en`, ')') = :title
			ORDER BY `title_ru`, `title_en`
		");

		$this->getEligibleShowsWithScoreQuery = $this->pdo->prepare("
			$selectFields,
			MATCH(`title_ru`, `title_en`) AGAINST(:title) AS `score`
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

	public function getShowById(int $id){
		$args = array(
			':id' => $id
		);

		return $this->execute(
			$this->getShowByIdQuery,
			$args,
			\QueryTraits\Type::Read(),
			\QueryTraits\Approach::One()
		);
	}

	public function getShowByAlias(string $alias){
		$args = array(
			':alias' => $alias
		);

		return $this->execute(
			$this->getShowByAliasQuery,
			$args,
			\QueryTraits\Type::Read(),
			\QueryTraits\Approach::OneIfExists()
		);
	}

	public function getUserShows(int $user_id){
		$args = array(
			':user_id' => $user_id
		);

		return $this->execute(
			$this->getUserShowsQuery,
			$args,
			\QueryTraits\Type::Read(),
			\QueryTraits\Approach::Many()
		);
	}

	public function getEligibleShows(int $user_id, int $action){
		ShowAction::verify($action);
		
		$args = array(
			':user_id'		=> $user_id,
			':showAction'	=> $action !== ShowAction::Remove
		);

		return $this->execute(
			$this->getEligibleShowsQuery,
			$args,
			\QueryTraits\Type::Read(),
			\QueryTraits\Approach::Many()
		);
	}

	public function getEligibleShowByTitle(int $user_id, string $title, int $action){
		ShowAction::verify($action);
		
		$args = array(
			':user_id'		=> $user_id,
			':title'		=> $title,
			':showAction'	=> $action !== ShowAction::Remove
		);

		return $this->execute(
			$this->getEligibleShowByTitleQuery,
			$args,
			\QueryTraits\Type::Read(),
			\QueryTraits\Approach::OneIfExists()
		);
	}

	public function getEligibleShowsWithScore(int $user_id, string $title, int $action){
		ShowAction::verify($action);

		$args = array(
			':user_id'		=> $user_id,
			':title'		=> $title,
			':showAction'	=> $action !== ShowAction::Remove
		);

		return $this->execute(
			$this->getEligibleShowsWithScoreQuery,
			$args,
			\QueryTraits\Type::Read(),
			\QueryTraits\Approach::Many()
		);
	}

	public function getAliases(){
		$args = array();
		$shows = $this->execute(
			$this->getAllQuery,
			$args,
			\QueryTraits\Type::Read(),
			\QueryTraits\Approach::Many());

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

		$this->execute(
			$this->addShowQuery,
			$args,
			\QueryTraits\Type::Write(),
			\QueryTraits\Approach::One()
		);

		return $this->getLastInsertId();
	}

	public function updateShow(Show $show){
		$args = array(
			':title_ru'				=> $show->getTitleRu(),
			':title_en'				=> $show->getTitleEn(),
			':onAir'				=> $show->isOnAir() ? 'Y' : 'N',
			':lastAppearanceTime'	=> $show->getLastAppearanceTime()->format(parent::dateTimeAppFormat),
			':alias'				=> $show->getAlias()
		);

		$this->execute(
			$this->updateShowQuery,
			$args,
			\QueryTraits\Type::Write(),
			\QueryTraits\Approach::One()
		);
	}

	public function lockShowsWrite(){
		$this->pdo->query('LOCK TABLES `shows` WRITE');
	}

	public function unlockTables(){
		$this->pdo->query('UNLOCK TABLES');
	}
}
