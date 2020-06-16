<?php

namespace parser;

require_once(__DIR__.'/../lib/Tracer/TracerFactory.php');
require_once(__DIR__.'/Parser.php');
require_once(__DIR__.'/SeriesAboutInfo.php');
require_once(__DIR__.'/../lib/HTTPRequester/HTTPRequesterInterface.php');

class SeriesAboutParser extends Parser{
	private $tracer;
	
	const discussion_tag = '/<a href="\/series\/\w+\/season_\d+\/episode_\d+\/comments" class="item last">Обсуждение серии<\/a>/';
	const expected_tag_regex = '/<div class="expected">/';
	const title_ru_regex = '/<h1 class="title-ru">([\s\S]*?)<\/h1>/';
	const title_en_regex = '/<div class="title-en">([\s\S]*?)<\/div>/';
	
	public function __construct(
		\HTTPRequester\HTTPRequesterInterface $requester,
		\PDO $pdo
	){
		parent::__construct($requester);

		$this->tracer = \TracerFactory::getTracer(__CLASS__, $pdo);
	}

	private function singleRegexSearch($regex){
		$matches = array();
		$matchesCount = preg_match($regex, $this->pageSrc, $matches);
		if($matchesCount === false){
			$this->tracer->logfError(
				'[ERROR]', __FILE__, __LINE__,
				'preg_match has failed with code: [%s][%s]',
				$regex,
				preg_last_error()
			);

			$this->tracer->logDebug(
				'[o]', __FILE__, __LINE__,
				'Source:'.PHP_EOL.
				$this->pageSrc
			);

			throw new \Exception('preg_match has failed');
		}

		return $matches;
	}
	
	public function run(){
		$expected_res = $this->singleRegexSearch(self::expected_tag_regex);
		if(empty($expected_res) === false){
			return new SeriesAboutInfo(false, 1);
		}

		$title_ru_res = $this->singleRegexSearch(self::title_ru_regex);
		if(empty($title_ru_res)){
			return new SeriesAboutInfo(false, 2);
		}

		$mask = " \t\n\r\0\x0B\xC2\xA0"; # Standard trim list + &nbsp
		$title_ru = trim($title_ru_res[1], $mask);

		$title_en_res = $this->singleRegexSearch(self::title_en_regex);
		if(empty($title_en_res)){
			return new SeriesAboutInfo(false, 3, $title_ru);
		}

		$title_en = trim($title_en_res[1], $mask);

		if($title_ru === $title_en){ 
			// Either series wasn't published or title_ru is the same as tile_en
			// Trying to determine

			$nextLinkActive = $this->singleRegexSearch(self::discussion_tag);
			if(empty($nextLinkActive)){
				return new SeriesAboutInfo(false, 4, $title_ru, $title_en);
			}
		}

		return new SeriesAboutInfo(true, null, $title_ru, $title_en);
	}
}

		

