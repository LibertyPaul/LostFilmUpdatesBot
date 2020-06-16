<?php

namespace DAL;

require_once(__DIR__.'/../CommonAccess.php');
require_once(__DIR__.'/TrackBuilder.php');
require_once(__DIR__.'/Track.php');


class TracksAccess extends CommonAccess{
	private $getTracksByUserQuery;
	private $getTracksByShowQuery;
	private $addTrackQuery;
	private $deleteTrackQuery;

	public function __construct(\PDO $pdo){
		parent::__construct(
			$pdo,
			new TrackBuilder()
		);

		$selectFields = "
			SELECT
				`tracks`.`user_id`,
				`tracks`.`show_id`,
				DATE_FORMAT(`tracks`.`created`, '".parent::dateTimeDBFormat."') AS createdStr
		";

		$this->getTracksByUserQuery = $this->pdo->prepare("
			$selectFields
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
			VALUES (:user_id, :show_id, STR_TO_DATE(:created, '".parent::dateTimeDBFormat."'))
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

		return $this->execute(
			$this->getTracksByUserQuery,
			$args,
			\QueryTraits\Type::Read(),
			\QueryTraits\Approach::Many()
		);
	}

	public function getTracksByShow(int $show_id){
		$args = array(
			':show_id' => $show_id
		);

		return $this->execute(
			$this->getTracksByShowQuery,
			$args,
			\QueryTraits\Type::Read(),
			\QueryTraits\Approach::Many()
		);
	}

	public function addTrack(Track $track){
		$args = array(
			':user_id' => $track->getUserId(),
			':show_id' => $track->getShowId(),
			':created' => $track->getCreationTime()->format(parent::dateTimeAppFormat)
		);

		$this->execute(
			$this->addTrackQuery,
			$args,
			\QueryTraits\Type::Write(),
			\QueryTraits\Approach::One()
		);
	}

	public function deleteTrack(Track $track){
		$args = array(
			':user_id' => $track->getUserId(),
			':show_id' => $track->getShowId()
		);

		$this->execute(
			$this->deleteTrackQuery,
			$args,
			\QueryTraits\Type::Write(),
			\QueryTraits\Approach::One()
		);
	}
}
