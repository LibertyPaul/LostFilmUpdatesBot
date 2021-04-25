<?php

namespace DAL;

class Show{
	private $id;
	private $alias;
	private $title_ru;
	private $title_en;
	private $onAir;
	private $firstAppearanceTime;
	private $lastAppearanceTime;

	public function __construct(
		?int $id,
		string $alias,
		string $title_ru,
		string $title_en,
		bool $onAir,
		\DateTimeInterface $firstAppearanceTime,
		\DateTimeInterface $lastAppearanceTime
	){
		$this->id					= $id;
		$this->alias				= $alias;
		$this->title_ru				= $title_ru;
		$this->title_en				= $title_en;
		$this->onAir				= $onAir;
		$this->firstAppearanceTime	= $firstAppearanceTime;
		$this->lastAppearanceTime	= $lastAppearanceTime;
	}

	public function getId(){
		return $this->id;
	}

	public function setId(int $id){
		if($this->id !== null){
			throw new \LogicException("Trying to change an existing Show ID: [$this->{id}] --> [$id].");
		}

		$this->id = $id;
	}

	public function getAlias(){
		return $this->alias;
	}

	public function getTitleRu(){
		return $this->title_ru;
	}

	public function getTitleEn(){
		return $this->title_en;
	}

	public function getFullTitle(string $format = "%s (%s)"){
		return sprintf($format, $this->getTitleRu(), $this->getTitleEn());
	}

	public function isOnAir(){
		return $this->onAir;
	}

	public function getFirstAppearanceTime(){
		return $this->firstAppearanceTime;
	}

	public function getLastAppearanceTime(){
		return $this->lastAppearanceTime;
	}

	public function __toString(){
		$idStr = is_null($this->getId()) ? 'Null' : strval($this->getId());
		$onAirStr = $this->isOnAir() ? 'Y' : 'N';
		$firstAppearanceTimeStr = $this->getFirstAppearanceTime()->format('d-M-Y H:i:s.u');
		$lastAppearanceTimeStr = $this->getLastAppearanceTime()->format('d-M-Y H:i:s.u');

		$result =
			'**********************[Show]**********************'				.PHP_EOL.
			sprintf("Show ID:               [%s]", $idStr)						.PHP_EOL.
			sprintf("Alias:                 [%s]", $this->getAlias())			.PHP_EOL.
			sprintf("Title Ru:              [%s]", $this->getTitleRu())			.PHP_EOL.
			sprintf("Title En:              [%s]", $this->getTitleEn())			.PHP_EOL.
			sprintf("On Air:                [%s]", $onAirStr)					.PHP_EOL.
			sprintf("First Appearance Time: [%s]", $firstAppearanceTimeStr)		.PHP_EOL.
			sprintf("Last Appearance Time:  [%s]", $lastAppearanceTimeStr)		.PHP_EOL.
			'**************************************************';
		
		return $result;
	}
}
