<?php

namespace DAL;

require_once(__DIR__.'/Show.php');

class MatchedShow extends Show{
	private $score;

	public function __construct(
		?int $id,
		string $alias,
		string $title_ru,
		string $title_en,
		bool $onAir,
		\DateTimeInterface $firstAppearanceTime,
		\DateTimeInterface $lastAppearanceTime,
		float $score
	){
		parent::__construct(
			$id,
			$alias,
			$title_ru,
			$title_en,
			$onAir,
			$firstAppearanceTime,
			$lastAppearanceTime
		);

		assert($score >= 0.0);
		$this->score = $score;
	}

	public function getScore(): float {
		return $this->score;
	}
}
