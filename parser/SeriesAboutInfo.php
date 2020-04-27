<?php

namespace parser;

class SeriesAboutInfo{
	private $ready;
	private $reason;

	private $title_ru;
	private $title_en;

	public function __construct(
		bool $ready,
		int $reason = null,
		string $title_ru = "",
		string $title_en = ""
	){
		$this->ready = $ready;
		$this->reason = $reason;
		$this->title_ru = $title_ru;
		$this->title_en = $title_en;
	}

	public function isReady(){
		return $this->ready;
	}

	public function getReason(){
		return $this->reason;
	}

	public function getTitleRu(){
		return $this->title_ru;
	}

	public function getTitleEn(){
		return $this->title_en;
	}
}
