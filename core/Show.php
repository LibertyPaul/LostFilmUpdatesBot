<?php

namespace core;

class Show{
	private $alias;
	private $title_ru;
	private $title_en;
	private $onAir;

	public function __construct($alias, $title_ru, $title_en, $onAir){
		assert(is_string($alias));
		assert(is_string($title_ru));
		assert(is_string($title_en));
		assert($onAir === 'Y' || $onAir === 'N');

		$this->alias	= $alias;
		$this->title_ru	= $title_ru;
		$this->title_en	= $title_en;
		$this->onAir	= $onAir;
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

	public function getOnAir(){
		return $this->onAir;
	}

	public function __toString(){
		$result =
			'**************[Show]**************'			.PHP_EOL.
			sprintf("Alias:    [%s]", $this->getAlias())	.PHP_EOL.
			sprintf("Title RU: [%s]", $this->getTitleRu())	.PHP_EOL.
			sprintf("Title En: [%s]", $this->getTitleEn())	.PHP_EOL.
			sprintf("On Air:   [%s]", $this->getOnAir())	.PHP_EOL.
			'***********************************';
		
		return $result;
	}
}
