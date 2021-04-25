<?php

namespace DAL;

require_once(__DIR__.'/../CommonAccess.php');
require_once(__DIR__.'/SeriesBuilder.php');
require_once(__DIR__.'/Series.php');


class SeriesAccess extends CommonAccess{
	private $getSeriesByIdQuery;
	private $getSeriesByAliasSeasonSeriesQuery;

	private $addSeriesQuery;
    private $getLastSeriesQuery;
    private $updateSeriesQuery;

    public function __construct(\PDO $pdo){
		parent::__construct(
			$pdo,
			new SeriesBuilder()
		);

		$selectFields = "
			SELECT
				`series`.`id`,
				DATE_FORMAT(`series`.`firstSeenAt`, '".parent::dateTimeDBFormat."') AS firstSeenAtStr,
				`series`.`show_id`,
				`series`.`seasonNumber`,
				`series`.`seriesNumber`,
				`series`.`title_ru`,
				`series`.`title_en`,
				`series`.`ready`,
				`series`.`suggestedURL`
		";

		$this->getSeriesByIdQuery = $this->pdo->prepare("
			$selectFields
			FROM `series`
			WHERE `id` = :id
		");

		$this->getSeriesByAliasSeasonSeriesQuery = $this->pdo->prepare("
			$selectFields
			FROM `series`
			JOIN `shows` ON	`series`.`show_id` = `shows`.`id`
			WHERE	`shows`.`alias`			=	:alias
			AND		`series`.`seasonNumber`	=	:seasonNumber
			AND 	`series`.`seriesNumber`	=	:seriesNumber
		");

		$this->getLastSeriesQuery = $this->pdo->prepare("
			$selectFields
			FROM `series`
			WHERE `show_id` = :show_id
			AND `ready` = 'Y'
			ORDER BY `seasonNumber` DESC, `seriesNumber` DESC
			LIMIT 1
		");

		$this->addSeriesQuery = $this->pdo->prepare("
			INSERT INTO `series` (
				`firstSeenAt`,
				`show_id`,
				`seasonNumber`,
				`seriesNumber`,
				`title_ru`,
				`title_en`,
				`ready`,
				`suggestedURL`
			)
			VALUES (
				STR_TO_DATE(:firstSeenAtStr, '".parent::dateTimeDBFormat."'),
				:show_id,
				:seasonNumber,
				:seriesNumber,
				:title_ru,
				:title_en,
				:ready,
				:suggestedURL
			)
		");

		$this->updateSeriesQuery = $this->pdo->prepare("
			UPDATE `series`
			SET	`title_ru`		= :title_ru,
				`title_en`		= :title_en,
				`ready`			= :ready
				`suggestedURL`	= :suggestedURL
			WHERE `id`			= :id
		");
	}

	public function getSeriesById(int $id){
		$args = array(
			':id' => $id
		);

		return $this->execute(
			$this->getSeriesByIdQuery,
			$args,
			\QueryTraits\Type::Read(),
			\QueryTraits\Approach::One()
		);
	}

	public function getSeriesByAliasSeasonSeries(string $alias, int $seasonNumber, int $seriesNumber){
		$args = array(
			':alias'		=> $alias,
			':seasonNumber'	=> $seasonNumber,
			':seriesNumber'	=> $seriesNumber
		);

		return $this->execute(
			$this->getSeriesByAliasSeasonSeriesQuery,
			$args,
			\QueryTraits\Type::Read(),
			\QueryTraits\Approach::OneIfExists());
	}

	public function getLastSeries(int $showID){
		$args = array(
			':show_id' => $showID
		);

		return $this->execute(
			$this->getLastSeriesQuery,
			$args, 
			\QueryTraits\Type::Read(),
			\QueryTraits\Approach::OneIfExists()
		);
	}

	public function addSeries(Series $series){
		if($series->getId() !== null){
			throw new \RuntimeException("ID is present in a newly submitted series");
		}

		$args = array(
			':firstSeenAtStr'	=> $series->getFirstSeenAt()->format(parent::dateTimeAppFormat),
			':show_id'			=> $series->getShowId(),
			':seasonNumber'		=> $series->getSeasonNumber(),
			':seriesNumber'		=> $series->getSeriesNumber(),
			':title_ru'			=> $series->getTitleRu(),
			':title_en'			=> $series->getTitleEn(),
			':ready'			=> $series->isReady() ? 'Y' : 'N',
			':suggestedURL'		=> $series->getSuggestedURL()
		);

		return $this->execute(
			$this->addSeriesQuery,
			$args,
			\QueryTraits\Type::Write(),
			\QueryTraits\Approach::One()
		);

	}

	public function updateSeries(Series $series){
		if($series->getId() === null){
			throw new \RuntimeException("ID is absent in 'update episode' request");
		}

		$args = array(
			':title_ru'		=> $series->getTitleRu(),
			':title_en'		=> $series->getTitleEn(),
			':ready'		=> $series->isReady() ? 'Y' : 'N',
			':id'			=> $series->getId(),
			':suggestedURL'	=> $series->getSuggestedURL()
		);

		$this->execute(
			$this->updateSeriesQuery,
			$args,
			\QueryTraits\Type::Write(),
			\QueryTraits\Approach::One()
		);
	}

	public function lockSeriesWriteShowsRead(){
		$this->pdo->query('LOCK TABLES `series` WRITE, shows READ');
	}

	public function unlockTables(){
		$this->pdo->query('UNLOCK TABLES');
	}
}
