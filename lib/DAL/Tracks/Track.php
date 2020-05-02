<?php

namespace DAL;

class Track{
	private $user_id;
	private $show_id;
	private $creationTime;

	public function __construct(
		int $user_id,
		int $show_id,
		\DateTimeInterface $creationTime = null
	){
		$this->user_id = $user_id;
		$this->show_id = $show_id;

		if($creationTime === null){
			$this->creationTime = new \DateTimeImmutable();
		}
		else{
			$this->creationTime = $creationTime;
		}
	}

	public function getUserId(){
		return $this->user_id;
	}

	public function getShowId(){
		return $this->show_id;
	}

	public function getCreationTime(){
		return $this->creationTime;
	}

	public function __toString(){
		$creationTime = $this->creationTime->format('d.m.Y H:i:s');

		$result =
			'~~~~~~~~~~~~~~[Track]~~~~~~~~~~~~~~'				.PHP_EOL.
			sprintf('User Id:       [%d]', $this->getUserId())	.PHP_EOL.
			sprintf('Show Id:       [%d]', $this->getShowId())	.PHP_EOL.
			sprintf('Creation Time: [%s]', $creationTime)		.PHP_EOL.
			'~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~';

		return $result;
	}
}
