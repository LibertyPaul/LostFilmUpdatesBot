<?php

require_once(realpath(dirname(__FILE__))."/config/stuff.php");
require_once(realpath(dirname(__FILE__)).'/Parser.php');
require_once(realpath(dirname(__FILE__)).'/ShowAboutParser.php');


class ShowParser extends Parser{
	protected $getShowIdQuery;
	protected $addShowQuery;
	protected $updateShowStateQuery;
	protected $showAboutParser;
	
	const showPageTemplate = 'https://www.lostfilm.tv/browse.php?cat=#url_id';

	public function __construct($pageEncoding = "utf-8"){
		parent::__construct($pageEncoding);
		$pdo = createPDO();
		$this->showAboutParser = new ShowAboutParser('CP1251');
		
		$this->getShowIdQuery = $pdo->prepare('
			SELECT `id`
			FROM `shows`
			WHERE
				STRCMP(`title_ru`, :title_ru) = 0
			AND	STRCMP(`title_en`, :title_en) = 0
		');
		
		$this->addShowQuery = $pdo->prepare('
			INSERT INTO `shows` (title_ru, title_en)
			VALUES (:title_ru, :title_en)
		');
		
		$this->updateShowStateQuery = $pdo->prepare('
			UPDATE `shows`
			SET `onAir` = :onAir
			WHERE `id` = :id
		');
	}
	
	protected function getShowId($title_ru, $title_en){
		$this->getShowIdQuery->execute(
			array(
				':title_ru' => $title_ru,
				':title_en' => $title_en
			)
		);
		
		$res = $this->getShowIdQuery->fetchAll();
		
		if(count($res) > 0){
			return $res[0]['id'];
		}
		
		return null;
	}
	
	protected function parseShowList(){ // -> array(url_id => array(title_ru => '', title_en => ''), ...)
		$regexp = '/<a href="\/browse\.php\?cat=_?(\d+)" class="bb_a">([^<]*)<br><span>\(([^(]*)\)<\/span><\/a>/';
		$matches = array();
		$matchesCount = preg_match_all($regexp, $this->pageSrc, $matches);
		if($matchesCount === false){
			throw new Exception("preg_match_all error: ".preg_last_error());
		}
		
		$result = array();
		
		for($i = 0; $i < $matchesCount; ++$i){
			$url_id	= intval($matches[1][$i]);
			$title_ru = $matches[2][$i];
			$title_en = $matches[3][$i];
			
			$result[$url_id] = array(
				'title_ru' => $title_ru,
				'title_en' => $title_en
			);
		}
		
		return $result;
	}
	
	protected function getShowPageURL($url_id){
		if(is_int($url_id) == false){
			throw new StdoutTextException('$url_id should be of an integer type.');
		}
		
		return str_replace('#url_id', $url_id, self::showPageTemplate);
	}
	
	public function run(){
		$showList = $this->parseShowList();
		foreach($showList as $url_id => $titles){
			try{
				$showId = $this->getShowId($titles['title_ru'], $titles['title_en']);
				if($showId === null){
					echo "New show: $titles[title_ru] ($titles[title_en])".PHP_EOL;
					$this->addShowQuery->execute(
						array(
							':title_ru' => $titles['title_ru'],
							':title_en' => $titles['title_en']
						)
					);
				}
				
				$url = $this->getShowPageURL($url_id);
				$this->showAboutParser->loadSrc($url);
				
				$this->updateShowStateQuery->execute(
					array(
						':id' 		=> $showId,
						':onAir'	=> $this->showAboutParser->run()
					)
				);
			}
			catch(Exception $ex){
				echo '[EXCEPTION]: '.$ex->getMessage().PHP_EOL;
			}
		}
	}
}
	







