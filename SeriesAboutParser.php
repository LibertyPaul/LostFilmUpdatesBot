<?php

require_once(__DIR__.'/Tracer.php');
require_once(__DIR__.'/Parser.php');
require_once(__DIR__.'/HTTPRequesterInterface.php');


class SeriesAboutParser extends Parser{
	private $tracer;

	const title_ru_regex = '/<div class="title-ru">([\s\S]*?)<\/div>/';
	const title_en_regex = '/<div class="title-en">([\s\S]*?)<\/div>/';
	


	public function __construct(HTTPRequesterInterface $requester){
		parent::__construct($requester);

		$this->tracer = new Tracer(__CLASS__);
	}

	private function singleRegexSearch($regex, $src){
		$matches = array();
		$matchesCount = preg_match($regex, $src, $matches);
		if($matchesCount === false){
			$this->tracer->log('[ERROR]', __FILE__, __LINE__, 'preg_match has failed with code: '.preg_last_error());
			$this->tracer->log('[ERROR]', __FILE__, __LINE__, PHP_EOL.$this->pageSrc);
			throw new Exception('preg_match has failed');
		}

		if($matchesCount === 0){
			$this->tracer->log('[DATA ERROR]', __FILE__, __LINE__, "Page src doesn't match pattern");
			$this->tracer->log('[ERROR]', __FILE__, __LINE__, PHP_EOL.$this->pageSrc);
			throw new Exception("Link doesn't match pattern");
		}

		assert($matchesCount === 1);

		return $matches;
	}
	
	public function run(){
		$title_ru_res = $this->singleRegexSearch(self::title_ru_regex, $this->pageSrc);
		$title_ru = $title_ru_res[1];

		$title_en_res = $this->singleRegexSearch(self::title_en_regex, $this->pageSrc);
		$title_en = $title_en_res[1];

		return array(
			'title_ru' => $title_ru,
			'title_en' => $title_en
		);
	}
}

		

