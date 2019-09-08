<?php

namespace parser;

require_once(__DIR__.'/../lib/Tracer/Tracer.php');
require_once(__DIR__.'/Parser.php');
require_once(__DIR__.'/../lib/HTTPRequester/HTTPRequesterInterface.php');

abstract class SeriesStatus{
	const Ready = 0;
	const NotReady = 1;
}

class SeriesAboutParser extends Parser{
	private $tracer;
	
	const discussion_tag = '/<a href="\/series\/\w+\/season_\d+\/episode_\d+\/comments" class="item last">Обсуждение серии<\/a>/';
	const expected_tag_regex = '/<div class="expected">/';
	const title_ru_regex = '/<h1 class="title-ru">([\s\S]*?)<\/h1>/';
	const title_en_regex = '/<div class="title-en">([\s\S]*?)<\/div>/';
	


	public function __construct(\HTTPRequester\HTTPRequesterInterface $requester){
		parent::__construct($requester);

		$this->tracer = new \Tracer(__CLASS__);
	}

	private function singleRegexSearch($regex){
		$matches = array();
		$matchesCount = preg_match($regex, $this->pageSrc, $matches);
		if($matchesCount === false){
			$this->tracer->logError(
				'[ERROR]', __FILE__, __LINE__,
				'preg_match has failed with code: '.preg_last_error()	.PHP_EOL.
				"Regex string: '$regex'"								.PHP_EOL.
				'Source:'												.PHP_EOL.
				$this->pageSrc
			);

			throw new \Exception('preg_match has failed');
		}

		return $matches;
	}
	
	public function run(){
		$expected_res = $this->singleRegexSearch(self::expected_tag_regex);
		if(empty($expected_res) === false){
			return array(
				'status'	=> SeriesStatus::NotReady,
				'why'		=> 1
			);
		}

		$title_ru_res = $this->singleRegexSearch(self::title_ru_regex);
		if(empty($title_ru_res)){
			return array(
				'status'	=> SeriesStatus::NotReady,
				'why'		=> 2
			);
		}

		$title_en_res = $this->singleRegexSearch(self::title_en_regex);
		if(empty($title_en_res)){
			return array(
				'status'	=> SeriesStatus::NotReady,
				'why'		=> 3
			);
		}

		$mask = " \t\n\r\0\x0B\xC2\xA0"; # Standard trim list + &nbsp

		$title_ru = trim($title_ru_res[1], $mask);
		$title_en = trim($title_en_res[1], $mask);

		if($title_ru === $title_en){ 
			// Either series wasn't published or title_ru is the same as tile_en
			// Trying to determine

			$nextLinkActive = $this->singleRegexSearch(self::discussion_tag);
			if(empty($nextLinkActive)){
				return array(
					'status'	=> SeriesStatus::NotReady,
					'why'		=> 4
				);
			}
		}

		return array(
			'status'	=> SeriesStatus::Ready,
			'title_ru'	=> $title_ru,
			'title_en'	=> $title_en
		);
	}
}

		

