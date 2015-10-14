<?php

require_once(realpath(dirname(__FILE__)).'/config/stuff.php');
require_once(realpath(dirname(__FILE__)).'/TelegramBot.php');
require_once(realpath(dirname(__FILE__)).'/Parser.php');


class SeriesParser extends Parser{
	protected $pdo;
	private $lockFile;

	public function __construct($pageEncoding = "utf-8"){
		parent::__construct($pageEncoding);
		$this->pdo = createPDO();
	}

	
	protected function getShowUrlId($show_id){
		$getUrlId = $this->pdo->prepare("
			SELECT `url_id`
			FROM `shows`
			WHERE `id` = :show_id
		");
		$res = $getUrlId->execute(
			array(
				':show_id' => $show_id
			)
		);
		if($res === false)
			throw new Exception("PDO execute2 error");
		if($getUrlId->rowCount() === 0)
			throw new Exception("No shows in table");
		
		$show = $getUrlId->fetchObject();
		return $show->url_id;
	}
		
		
	
	protected function isShowOnAir($show_id){
		//$url
	}
	
	
	protected function addShow($url_id, $titleRu, $titleEn){
		
	}
	
	protected function submitNewSeries($url_id, $seriesTimestamp, $seriesNameRu, $seriesNameEn, $seasonNumber, $seriesNumber){
		$getShowId = $this->pdo->prepare("
			SELECT `id`
			FROM `shows`
			WHERE `url_id` = :url_id
		");
		
		$getLatestSeriesTimestamp = $this->pdo->prepare("
			SELECT `latestSeriesTimestamp`
			FROM `shows`
			WHERE `id` = :show_id
		");			
		
		$setLatestSeriesTimestamp = $this->pdo->prepare("
			UPDATE `shows` SET
				`latestSeriesTimestamp` = :latestSeriesTimestamp
			WHERE `id` = :show_id
		");
		
		
		$res = $getShowId->execute(
			array(
				'url_id' => $url_id
			)
		);
		if($res === false)
			throw new Exception("PDO execute1 error");
		if($getShowId->rowCount() === 0)
			throw new Exception("Show not found by url_id = $url_id.");
		
		$show = $getShowId->fetchObject();
		$show_id = intval($show->id);
		
		$res = $getLatestSeriesTimestamp->execute(
			array(
				':show_id' => $show_id
			)
		);
		if($res === false)
			throw new Exception("PDO execute2 error");
		if($getLatestSeriesTimestamp->rowCount() === 0)
			throw new Exception("show_id was not found");
			
		$latestSeriesProperties = $getLatestSeriesTimestamp->fetchObject();
				
		$latestSeriesTimestamp = 0;
		if(isset($latestSeriesProperties->latestSeriesTimestamp))
			$latestSeriesTimestamp = intval($latestSeriesProperties->latestSeriesTimestamp);
			
		//echo "$latestSeriesTimestamp -> $seriesTimestamp\t$seriesNameRu $seasonNumber:$seriesNumber\n";
		if($latestSeriesTimestamp < $seriesTimestamp){
			$res = $setLatestSeriesTimestamp->execute(
				array(
					':latestSeriesTimestamp' => $seriesTimestamp,
					':show_id' 	=> $show_id
				)
			);
			if($res === false)
				throw new Exception("PDO execute3 error");
			
			
			TelegramBot::newSeriesEvent($show_id, $seasonNumber, $seriesNumber, $seriesNameRu);
			return true;
		}
		return false;
		
	}
	
	public function run(){// returs number of parsed series in the past. If === 0 we should parse older
		$regexp = '/<!--\t\t\t0*(\d+)\.0*(\d+)[\S\s]+?cat=_?(\d+)[\S\s]+?title="([^"]+)"[\S\s]+?<b>([^\(<]*)\s?\(?([^)<]*)?\)?[\S\s]+?Дата: <b>([^<]+)/';
		/*
			1. Сезон
			2. Серия // если === 99 - сезон целиком -> отбрасываем
			3. url_id
			4. название сериала рус TODO: remove leading space
			5. название серии рус
			6. [название серии англ]
			7. дата и время выхода
		*/
		
		
		$params = array();
		$parsedCount = preg_match_all($regexp, $this->pageSrc, $params);
		if($parsedCount === false)
			throw new Exception("preg_last_error: ".preg_last_error());
		
		
		$alreadyParsed = $parsedCount;
		
		for($seriesIndex = 0; $seriesIndex < $parsedCount; ++$seriesIndex){
			$seasonNumber = intval($params[1][$seriesIndex]);
			$seriesNumber = intval($params[2][$seriesIndex]);
			if($seriesNumber === 99)
				continue;
			
			$url_id = intval($params[3][$seriesIndex]);//если === 42 - LostFilm.Кинозал - игнорим
			if($url_id === 42)
				continue;
			$showTitleRu = 	$params[4][$seriesIndex];
			$showTitleRu = rtrim($showTitleRu);//костыль. сделать через регулярку не получилось
			
			$seriesTitleRu = $params[5][$seriesIndex];
			$seriesTitleEn = $params[6][$seriesIndex];
			$releaseDatetime = new DateTime($params[7][$seriesIndex]);
			
			$res = $this->submitNewSeries($url_id, $releaseDatetime->getTimestamp(), $seriesTitleRu, $seriesTitleEn, $seasonNumber, $seriesNumber);
			if($res)
				--$alreadyParsed;
				
			//echo "$res: $showTitleRu $seasonNumber:$seriesNumber\n";
				
						
		}
		return $alreadyParsed;
	}
}















