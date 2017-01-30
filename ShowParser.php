<?php
require_once(__DIR__.'/Parser.php');
require_once(__DIR__.'/config/stuff.php');
require_once(__DIR__.'/Exceptions/StdoutTextException.php');
require_once(__DIR__.'/ShowAboutParser.php');
require_once(__DIR__.'/EchoTracer.php');


class ShowParser extends Parser{
	private $getShowIdQuery;
	private $addShowQuery;
	private $updateOnAirQuery;

	private $showAboutParser;
	private $tracer;
	
	const showPageTemplate = 'https://old.lostfilm.tv/browse.php?cat=#url_id';

	public function __construct(HTTPRequesterInterface $requester, $pageEncoding = 'utf-8'){
		parent::__construct($requester, $pageEncoding);

		$this->tracer = new EchoTracer(__CLASS__);

		$pdo = createPDO();
		$this->showAboutParser = new ShowAboutParser($requester, 'CP1251');
		
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

		$this->updateOnAirQuery = $pdo->prepare('
			UPDATE `shows` SET `onAir` = :onAir WHERE `id` = :id
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
			$this->tracer->log('[DATA ERROR]', __FILE__, __LINE__, 'preg_match_all error: '.preg_last_error());
			$this->tracer->log('[DATA ERROR]', __FILE__, __LINE__, PHP_EOL.$this->pageSrc);
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
		assert(is_int($url_id));		
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
				$onAir = $this->isOnAir($url_id);
				$onAirFlag = $onAir ? 'Y' : 'N';
				if($showId === null){
					$this->tracer->log('[NEW SHOW]', __FILE__, __LINE__, "$titles[title_ru] ($titles[title_en])");
					
					$this->addShowQuery->execute(
						array(
							':title_ru' => $titles['title_ru'],
							':title_en' => $titles['title_en'],
							':onAir'	=> $onAirFlag
						)
					);
				}
				else{
					$this->updateOnAirQuery->execute(
						array(
							':onAir'	=> $onAirFlag,
							':id'		=> $showId
						)
					);
				}
			}
			catch(PDOException $ex){
				$this->tracer->logException('[DB ERROR]', $ex);
				$this->tracer->log('[DB ERROR]', __FILE__, __LINE__, "url_id = $url_id, showId = $showId, onAir = $onAir");
				$this->tracer->log('[DB ERROR]', __FILE__, __LINE__, PHP_EOL.print_r($titles, true));
			}
			catch(Exception $ex){
				$this->tracer->logException('[ERROR]', $ex);
				$this->tracer->log('[DB ERROR]', __FILE__, __LINE__, "url_id = $url_id, showId = $showId, onAir = $onAir");
				$this->tracer->log('[DB ERROR]', __FILE__, __LINE__, PHP_EOL.print_r($titles, true));
			}
		}
	}
}
	







