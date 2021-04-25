<?php

namespace DAL;

class Series{
	private $id;
	private $firstSeenAt;
	private $showId;
	private $seasonNumber;
	private $seriesNumber;
	private $titleRu;
	private $titleEn;
	private $ready;
	private $suggestedURL;

	public function __construct(
		?int $id,
		\DateTimeInterface $firstSeenAt,
		int $showId,
		int $seasonNumber,
		int $seriesNumber,
		string $titleRu,
		string $titleEn,
		bool $ready,
		?string $suggestedURL
	){
		$this->id			= $id;
		$this->firstSeenAt	= $firstSeenAt;
		$this->showId		= $showId;
		$this->seasonNumber	= $seasonNumber;
		$this->seriesNumber	= $seriesNumber;
		$this->titleRu		= $titleRu;
		$this->titleEn		= $titleEn;
		$this->ready		= $ready;
		$this->suggestedURL	= $suggestedURL;
	}

	public function getId(){
		return $this->id;
	}

	public function setId(int $id){
		$this->id = $id;
	}

	public function getFirstSeenAt(){
		return $this->firstSeenAt;
	}

	public function getShowId(){
		return $this->showId;
	}

	public function getSeasonNumber(){
		return $this->seasonNumber;
	}

	public function getSeriesNumber(){
		return $this->seriesNumber;
	}

	public function getTitleRu(){
		return $this->titleRu;
	}

	public function getTitleEn(){
		return $this->titleEn;
	}

	public function isReady(){
		return $this->ready;
	}

	public function setReady(){
		$this->ready = true;
	}

	public function getSuggestedURL(){
		return $this->suggestedURL;
	}

	public function __toString(){
		$idStr = is_null($this->getId()) ? 'Null' : strval($this->getId());
		$isReadyStr = $this->isReady() ? 'Y' : 'N';
		$firstSeenAtStr = $this->getFirstSeenAt()->format('d-M-Y H:i:s.u');

		$result =
			'==============[Series]=============='						.PHP_EOL.
			sprintf("Id:            [%s]", $this->getId())				.PHP_EOL.
			sprintf("First Seen At:	[%s]", $firstSeenAtStr)				.PHP_EOL.
			sprintf("Show ID:       [%s]", $this->getShowId())			.PHP_EOL.
			sprintf("Season Number:	[%s]", $this->getSeasonNumber())	.PHP_EOL.
			sprintf("Series Number: [%s]", $this->getSeriesNumber())	.PHP_EOL.
			sprintf("Title RU:      [%s]", $this->getTitleRu())			.PHP_EOL.
			sprintf("Title En:      [%s]", $this->getTitleEn())			.PHP_EOL.
			sprintf("Is Ready:      [%s]", $isReadyStr)					.PHP_EOL.
			sprintf("Suggested URL: [%s]", $this->getSuggestedURL())	.PHP_EOL.
			'====================================';
		
		return $result;
	}
}
