<?php
require_once(__DIR__.'/../lib/Tracer/Tracer.php');
require_once(__DIR__.'/Parser.php');
require_once(__DIR__.'/../lib/HTTPRequester/HTTPRequesterInterface.php');

class SeriesIsNotPublishedYet extends RuntimeException{}

class SeriesAboutParser extends Parser{
	private $tracer;
	
	const discussion_tag = '/<a href="\/series\/\w+\/season_\d+\/episode_\d+\/comments" class="item last">Обсуждение серии<\/a>/';
	const expected_tag_regex = '/<div class="expected">/';
	const title_ru_regex = '/<div class="title-ru">([\s\S]*?)<\/div>/';
	const title_en_regex = '/<div class="title-en">([\s\S]*?)<\/div>/';
	


	public function __construct(HTTPRequesterInterface $requester){
		parent::__construct($requester);

		$this->tracer = new \Tracer(__CLASS__);
	}

	private function singleRegexSearch($regex){
		$matches = array();
		$matchesCount = preg_match($regex, $this->pageSrc, $matches);
		if($matchesCount === false){
			$this->tracer->logError('[ERROR]', __FILE__, __LINE__, 'preg_match has failed with code: '.preg_last_error());
			$this->tracer->logError('[ERROR]', __FILE__, __LINE__, "Regex string: '$regex'");
			$this->tracer->logError('[ERROR]', __FILE__, __LINE__, 'Source:'.PHP_EOL.$this->pageSrc);
			throw new Exception('preg_match has failed');
		}

		return $matches;
	}
	
	public function run(){
		$expected_res = $this->singleRegexSearch(self::expected_tag_regex);
		if(empty($expected_res) === false){
			throw new SeriesIsNotPublishedYet('Series seems not to be ready yet. '.self::expected_tag_regex.' was found');
		}

		$title_ru_res = $this->singleRegexSearch(self::title_ru_regex);
		if(empty($title_ru_res)){
			throw new SeriesIsNotPublishedYet('Series seems not to be ready yet. '.self::title_ru_regex.' was not found');
		}

		$title_en_res = $this->singleRegexSearch(self::title_en_regex);
		if(empty($title_en_res)){
			throw new SeriesIsNotPublishedYet('Series seems not to be ready yet. '.self::title_en_regex.' was not found');
		}

		$title_ru = $title_ru_res[1];
		$title_en = $title_en_res[1];

		if($title_ru === $title_en){ // Either series wasn't published or title_ru is the same as tile_en
			// Trying to determine

			$nextLinkActive = $this->singleRegexSearch(self::discussion_tag);
			if(empty($nextLinkActive)){
				throw new SeriesIsNotPublishedYet('Series seems not to be ready yet. '.self::discussion_tag.' was not found');
			}
		}

		return array(
			'title_ru' => $title_ru_res[1],
			'title_en' => $title_en_res[1]
		);
	}
}

		

