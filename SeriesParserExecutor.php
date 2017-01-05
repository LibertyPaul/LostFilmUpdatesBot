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
		try{
			$this->seriesParser->loadSrc(self::$rssURL);
		}
		catch(HTTPException $ex){
			echo debug_tag('[HTTP ERROR]', __FILE__, __LINE__, $ex->getMessage()).PHP_EOL;
			exit;
		}
		
		$newSeriesList = $this->seriesParser->run();
		
		foreach($newSeriesList as $newSeries){
			try{
				$this->addSeriesQuery->execute($newSeries);
			}
			catch(PDOException $ex){
				echo debug_tag('[DB ERROR]', __FILE__, __LINE__).PHP_EOL;
				
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
				echo debug_tag('[UNKNOWN ERROR]', __FILE__, __LINE__, $ex->getMessage()).PHP_EOL;
			}
		}
	}
}


$requester = new HTTPRequester();
$parser = new SeriesParser($requester);
$SPE = new SeriesParserExecutor($parser);
$SPE->run();














