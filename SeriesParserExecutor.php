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
			catch(PDOException $ex){
				$date = date('Y.m.d H:i:s');
				echo "[DB ERROR]\t$date\t".__FILE__.':'.__LINE__.PHP_EOL;
				
				switch($ex->getCode()){
					case '02000': 
						echo 'Show wasn\'t found'.PHP_EOL;
						break;
					
					default:
						echo 'Unknown error code: '.$ex->getCode().PHP_EOL.$ex->getMessage().PHP_EOL;
						break;
				}
					
				print_r($newSeries);
				echo PHP_EOL.PHP_EOL;
			}
			catch(Exception $ex){
				$date = date('Y.M.d H:i:s');
				echo "[UNKNOWN ERROR]\t".__FILE__.':'.__LINE__."\t$date\t".$ex->getMessage().PHP_EOL;
			}
		}
	}
}

$parser = new SeriesParser();
$SPE = new SeriesParserExecutor($parser);
$SPE->run();














