<?php
require_once(__DIR__.'/config/stuff.php');
require_once(__DIR__.'/SeriesParser.php');

class SeriesParserExecutor{
	private static $rssURL = 'http://www.lostfilm.tv/rssdd.xml';
	private $seriesParser;
	private $addSeriesQuery;
	
	public function __construct(Parser $seriesParser){
		assert($seriesParser !== null);
		$this->seriesParser = $seriesParser;
		
		$pdo = createPDO();
		
		$this->addSeriesQuery = $pdo->prepare('
			CALL addSeriesIfNotExist(
				:showTitleRu,
				:showTitleEn,
				:seasonNumber,
				:seriesNumber,
				:seriesTitleRu,
				:seriesTitleEn
			);
		');

	}

	public function run(){
		$this->seriesParser->loadSrc(self::$rssURL);
		$newSeriesList = $this->seriesParser->run();
		
		foreach($newSeriesList as $newSeries){
			try{
				$this->addSeriesQuery->execute($newSeries);
			}
			catch(Exception $ex){
				echo __FILE__.':'.__LINE__."\t".$ex->getMessage().PHP_EOL;
			}
		}
	}

}

$parser = new SeriesParser();
$SPE = new SeriesParserExecutor($parser);
$SPE->run();
