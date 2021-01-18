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
		string $title_ru = null,
		string $title_en = null
	){
		$this->ready = $ready;
		$this->reason = $reason;
		$this->title_ru = $title_ru;
		$this->title_en = $title_en;
	}

	public function isReady(): bool{
		return $this->ready;
	}

	public function getReason(): ?int{
		return $this->reason;
	}

	public function getTitleRu(): ?string{
		return $this->title_ru;
	}

	public function getTitleEn(): ?string{
		return $this->title_en;
	}
}
