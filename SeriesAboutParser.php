<?php

require_once(__DIR__.'/Tracer.php');
require_once(__DIR__.'/Parser.php');
require_once(__DIR__.'/HTTPRequesterInterface.php');

class SeriesIsNotPublishedYet extends RuntimeException{}

class SeriesAboutParser extends Parser{
	private $tracer;
	
	const expected_tag_regex = '/<div class="expected">([\s\S]*?)<\/div>/';
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
			$this->tracer->log('[ERROR]', __FILE__, __LINE__, "Regex string: '$regex'");
			$this->tracer->log('[ERROR]', __FILE__, __LINE__, 'Source:'.PHP_EOL.$this->pageSrc);
			throw new Exception('preg_match has failed');
		}

		return $matches;
	}
	
	public function run(){
		$expected_res = $this->singleRegexSearch(self::expected_tag_regex, $this->pageSrc);
		if(empty($expected_res) === false){
			throw new SeriesIsNotPublishedYet('Series will be published a bit later');
		}
		

		$title_ru_res = $this->singleRegexSearch(self::title_ru_regex, $this->pageSrc);
		if(empty($title_ru_res)){
			$this->tracer->log('[ERROR]', __FILE__, __LINE__, "title-ru tag wasn't found on the page");
			$this->tracer->log('[ERROR]', __FILE__, __LINE__, 'Source:'.PHP_EOL.$this->pageSrc);
			throw new RuntimeException("title-ru tag wasn't found on the page");
		}

		$title_en_res = $this->singleRegexSearch(self::title_en_regex, $this->pageSrc);
		if(empty($title_en_res)){
			$this->tracer->log('[ERROR]', __FILE__, __LINE__, "title-en tag wasn't found on the page");
			$this->tracer->log('[ERROR]', __FILE__, __LINE__, 'Source:'.PHP_EOL.$this->pageSrc);
			throw new RuntimeException("title-en tag wasn't found on the page");
		}

		return array(
			'title_ru' => $title_ru_res[1],
			'title_en' => $title_en_res[1]
		);
	}
}

		

