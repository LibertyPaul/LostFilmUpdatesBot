<?php

namespace DAL;

require_once(__DIR__.'/../CommonAccess.php');
require_once(__DIR__.'/TrackBuilder.php');
require_once(__DIR__.'/Track.php');


class TracksAccess extends CommonAccess{
	private $getTracksByUserQuery;
	private $getUserTracksCount;
	private $getTracksByShowQuery;
	private $addTrackQuery;
	private $deleteTrackQuery;

	public function __construct(\Tracer $tracer, \PDO $pdo){
		parent::__construct(
			$tracer,
			$pdo,
			new TrackBuilder()
		);

		$selectFields = "
			SELECT
				`tracks`.`user_id`,
				`tracks`.`show_id`,
				DATE_FORMAT(`tracks`.`created`, '".parent::dateTimeDBFormat."') AS createdStr,
		";

		$this->getTracksByUserQuery = $this->pdo->prepare("
			$selectFields
			FROM `tracks`
			WHERE `tracks`.`user_id` = :user_id
		");

		$this->getUserTracksCount = $this->pdo->prepare("
			SELECT COUNT(*)
			FROM `tracks`
			WHERE `tracks`.`user_id` = :user_id
		");

		$this->getTracksByShowQuery = $this->pdo->prepare("
			$selectFields
			FROM `tracks`
			WHERE `tracks`.`show_id` = :show_id
		");

		$this->addTrackQuery = $this->pdo->prepare("
			INSERT INTO `tracks` (`user_id`, `show_id`, `created`)
			VALUES (:iser_id, :show_id, :created)
		");

		$this->deleteTrackQuery = $this->pdo->prepare("
			DELETE FROM `tracks`
			WHERE	`tracks`.`user_id` = :user_id
			AND		`tracks`.`show_id` = :show_id
		");
	}

	public function getTracksByUser(int $user_id){
		$args = array(
			':user_id' => $user_id
		);

		return $this->executeSearch($this->getTracksByUserQuery, $args, QueryApproach::MANY);
	}

	public function getUserTracksCount(int $user_id){
		$args = array(
			':user_id' => $user_id
		);

		$this->getTracksByUserQuery->exec($args);

		$res = $this->getTracksByUserQuery->fetch();
		if($res === false){
			throw new \RuntimeException("Unable to fetch user's tracks count");
		}

		return intval($res[0]);
	}

	public function getTracksByShow(int $show_id){
		$args = array(
			':show_id' => $show_id
		);

		return $this->executeSearch($this->getTracksByShowQuery, $args, QueryApproach::MANY);
	}

	public function addTrack(Track $track){
		$args = array(
			':user_id' => $track->getUserId(),
			':show_id' => $track->geShowId(),
			':created' => $user->getRegistrationTime()->format(parent::dateTimeAppFormat)
		);

		$this->executeInsertUpdateDelete($this->addTrackQuery, $args, QueryApproach::ONE);
	}

	public function deleteTrack(Track $track){
		$args = array(
			':user_id' => $track->getUserId(),
			':show_id' => $track->geShowId()
		);

		$this->executeInsertUpdateDelete($this->deleteTrackQuery, $args, QueryApproach::ONE);
	}
}
