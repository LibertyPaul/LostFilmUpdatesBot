<?php

namespace DAL;

require_once(__DIR__.'/../CommonAccess.php');
require_once(__DIR__.'/Series.php');


class SeriesAccess extends CommonAccess{
	private $pdo;

	private $getSeriesByIdQuery;
	private $getSeriesByAliasSeasonSeriesQuery;

	private $addSeriesQuery;

	public function __construct(\PDO $pdo){
		$this->pdo = $pdo;

		$selectFields = "
			SELECT	`series`.`id`,
					DATE_FORMAT(`series`.`firstSeenAt`, '".parent::dateTimeDBFormat."') AS firstSeenAtStr,
					`series`.`show_id`,
					`series`.`seasonNumber`,
					`series`.`seriesNumber`,
					`series`.`title_ru`,
					`series`.`title_en`,
					`series`.`ready`
			FROM	`series`
		";

		$this->getSeriesByIdQuery = $this->pdo->prepare("
			$selectFields
			WHERE `id` = :id
		");

		$this->getSeriesByAliasSeasonSeriesQuery = $this->pdo->prepare("
			$selectFields
			JOIN `shows` ON	`series`.`show_id` = `shows`.`id`
			WHERE	`shows`.`alias`			=	:alias
			AND		`series`.`seasonNumber`	=	:seasonNumber
			AND 	`series`.`seriesNumber`	=	:seriesNumber
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

	protected function buildObjectFromRow(array $row){
		$series = new Series(
			intval($row['id']),
			\DateTimeImmutable::createFromFormat(parent::dateTimeAppFormat, $row['firstSeenAtStr']),
			intval($row['show_id']),
			intval($row['seasonNumber']),
			intval($row['seriesNumber']),
			$row['title_ru'],
			$row['title_en'],
			$row['ready'] === 'Y'
		);

		return $series;
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
		return intval($this->pdo->lastInsertId());
	}

	public function updateSeries(Series $series){
		if($series->getId() === null){
			throw new \RuntimeException("ID is absent in 'update episode' request");
		}

		$args = array(
			':title_ru'	=> $series->getTitleRu(),
			':title_en'	=> $series->getTitleEn(),
			':ready'	=> $series->getReady() ? 'Y' : 'N',
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
