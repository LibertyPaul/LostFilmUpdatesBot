<?php
require_once(__DIR__.'/Parser.php');
require_once(__DIR__.'/config/stuff.php');
require_once(__DIR__.'/Exceptions/StdoutTextException.php');
require_once(__DIR__.'/ShowAboutParser.php');


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
			WHERE	STRCMP(`title_ru`, :title_ru) = 0
			AND		STRCMP(`title_en`, :title_en) = 0
		');
		
		$this->addShowQuery = $pdo->prepare('
			INSERT INTO `shows` (title_ru, title_en, onAir)
			VALUES (:title_ru, :title_en, :onAir)
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
		
		$res = $this->getShowIdQuery->fetch(PDO::FETCH_ASSOC);
		if($res === false){
			return null;
		}
		
		return $res['id'];
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
		if(is_int($url_id) === false){
			throw new StdoutTextException('$url_id should be of an integer type.');
		}
		
		return str_replace('#url_id', $url_id, self::showPageTemplate);
	}
	
	private function isOnAir($url_id){
		$url = $this->getShowPageURL($url_id);
		$this->showAboutParser->loadSrc($url);
		return $this->showAboutParser->run();
	}
	
	public function run(){
		$showList = $this->parseShowList();
		foreach($showList as $url_id => $titles){
			try{
				$showId = $this->getShowId($titles['title_ru'], $titles['title_en']);
				if($showId === null){
					echo "New show: $titles[title_ru] ($titles[title_en])".PHP_EOL;
					
					$onAir = $this->isOnAir($url_id) ? 'Y' : 'N';
					
					$this->addShowQuery->execute(
						array(
							':title_ru' => $titles['title_ru'],
							':title_en' => $titles['title_en'],
							':onAir'	=> $onAir
						)
					);
				}
			}
			catch(PDOException $ex){
				$date = date('Y.m.d H:i:s');
				echo "[DB ERROR]\t$date\t".__FILE__.':'.__LINE__.PHP_EOL;
				echo "\tError code: ".$ex->getCode().PHP_EOL;
				echo "\t".$ex->getMessage().PHP_EOL;
				echo "\turl_id = $url_id, showId = $showId, onAir = $onAir".PHP_EOL;
				print_r($titles);
			}
			catch(Exception $ex){
				$date = date('Y.m.d H:i:s');
				echo "[ERROR]\t$date\t".__FILE__.':'.__LINE__.PHP_EOL;
				echo "\t".$ex->getMessage().PHP_EOL;
			}
		}
	}
}
	







