<?php

namespace DAL;

require_once(__DIR__.'/../CommonAccess.php');
require_once(__DIR__.'/SeriesBuilder.php');
require_once(__DIR__.'/Series.php');


class SeriesAccess extends CommonAccess{
	private $getSeriesByIdQuery;
	private $getSeriesByAliasSeasonSeriesQuery;

	private $addSeriesQuery;

	public function __construct(\Tracer $tracer, \PDO $pdo){
		parent::__construct(
			$tracer,
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
				`series`.`ready`
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
				`ready`
			)
			VALUES (
				STR_TO_DATE(:firstSeenAtStr, '".parent::dateTimeDBFormat."'),
				:show_id,
				:seasonNumber,
				:seriesNumber,
				:title_ru,
				:title_en,
				:ready
			)
		");

		$this->updateSeriesQuery = $this->pdo->prepare("
			UPDATE `series`
			SET	`title_ru`	= :title_ru,
				`title_en`	= :title_en,
				`ready`		= :ready
			WHERE `id`		= :id
		");
	}

	public function getSeriesById(int $id){
		$args = array(
			':id' => $id
		);

		return $this->executeSearch($this->getSeriesByIdQuery, $args, QueryApproach::ONE);
	}

	public function getSeriesByAliasSeasonSeries(string $alias, int $seasonNumber, int $seriesNumber){
		$args = array(
			':alias'		=> $alias,
			':seasonNumber'	=> $seasonNumber,
			':seriesNumber'	=> $seriesNumber
		);

		return $this->executeSearch($this->getSeriesByAliasSeasonSeriesQuery, $args, QueryApproach::ONE_IF_EXISTS);
	}

	public function getLastSeries(int $showID){
		$args = array(
			':show_id' => $showID
		);

		return $this->executeSearch($this->getLastSeriesQuery, $args, QueryApproach::ONE_IF_EXISTS);
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
			':ready'			=> $series->isReady() ? 'Y' : 'N'
		);

		$this->executeInsertUpdateDelete($this->addSeriesQuery, $args, QueryApproach::ONE);
		return $this->getLastInsertId();
	}

	public function updateSeries(Series $series){
		if($series->getId() === null){
			throw new \RuntimeException("ID is absent in 'update episode' request");
		}

		$args = array(
			':title_ru'	=> $series->getTitleRu(),
			':title_en'	=> $series->getTitleEn(),
			':ready'	=> $series->isReady() ? 'Y' : 'N',
			':id'		=> $series->getId()
		);

		$this->executeInsertUpdateDelete($this->updateSeriesQuery, $args, QueryApproach::ONE);
	}

	public function lockSeriesWriteShowsRead(){
		$this->pdo->query('LOCK TABLES `series` WRITE, shows READ');
	}

	public function unlockTables(){
		$this->pdo->query('UNLOCK TABLES');
	}
}
