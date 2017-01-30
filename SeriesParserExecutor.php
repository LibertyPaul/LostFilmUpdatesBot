<?php
require_once(__DIR__.'/config/stuff.php');
require_once(__DIR__.'/SeriesParser.php');
require_once(__DIR__.'/EchoTracer.php');

class SeriesParserExecutor{
	private static $rssURL = 'http://old.lostfilm.tv/rss.xml';
	private $seriesParser;
	private $addSeriesQuery;
	private $tracer;
	
	public function __construct(Parser $seriesParser){
		assert($seriesParser !== null);
		$this->seriesParser = $seriesParser;

		$this->tracer = new EchoTracer(__CLASS__);
		
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
			$this->tracer->logException('[HTTP ERROR]', $ex);
			exit;
		}
		
		$newSeriesList = $this->seriesParser->run();
		
		foreach($newSeriesList as $newSeries){
			try{
				$this->addSeriesQuery->execute($newSeries);
			}
			catch(PDOException $ex){
				$this->tracer->logException('[DB ERROR]', $ex);
			
				switch($ex->getCode()){
					case '02000':
						$this->tracer->log('[WARNING]', __FILE__, __LINE__, 'Show wasn\'t found');
						break;
					
					default:
						$this->tracer->log('[ERROR]', __FILE__, __LINE__, 'Unknown error code: '.$ex->getCode().PHP_EOL.$ex->getMessage().PHP_EOL);
						break;
				}


				$this->tracer->log('[NEW SERIES]', __FILE__, __LINE__, PHP_EOL.print_r($newSeries, true));
			}
			catch(Exception $ex){
				$this->tracer->logException('[UNKNOWN ERROR]', $ex);
			}
		}
	}
}


$requester = new HTTPRequester();
$parser = new SeriesParser($requester);
$SPE = new SeriesParserExecutor($parser);
$SPE->run();














