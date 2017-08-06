<?php
require_once(__DIR__.'/../lib/ErrorHandler.php');
require_once(__DIR__.'/../lib/ExceptionHandler.php');

require_once(__DIR__.'/ParserPDO.php');
require_once(__DIR__.'/SeriesParser.php');
require_once(__DIR__.'/../lib/Tracer/Tracer.php');
require_once(__DIR__.'/SeriesAboutParser.php');
require_once(__DIR__.'/../lib/HTTPRequester/HTTPRequester.php');

class SeriesParserExecutor{
	const rssURL = 'https://www.lostfilm.tv/rss.xml'; // TODO: move URL to `config`
	private $pdo;
	private $seriesParser;
	private $seriesAboutsParser;
	private $addSeriesQuery;
	private $tracer;
	
	public function __construct(Parser $seriesParser, Parser $seriesAboutParser){
		assert($seriesParser !== null);
		$this->seriesParser = $seriesParser;

		assert($seriesAboutParser !== null);
		$this->seriesAboutParser = $seriesAboutParser;

		$this->tracer = new \Tracer(__CLASS__);
		
		$this->pdo = ParserPDO::getInstance();
		
		$this->addSeriesQuery = $this->pdo->prepare('
			INSERT INTO `series` (show_id, seasonNumber, seriesNumber, title_ru, title_en)
			SELECT id, :seasonNumber, :seriesNumber, :title_ru, :title_en
			FROM `shows`
			WHERE `alias` = :alias
		');

		$this->wasSeriesNotificationSentQuery = $this->pdo->prepare('
			SELECT COUNT(*)
			FROM `series`
			JOIN `shows` ON `series`.`show_id` = `shows`.`id`
			WHERE 	`shows`.`alias`	= :alias
			AND		`series`.`seasonNumber`	= :seasonNumber
			AND		`series`.`seriesNumber`	= :seriesNumber
		');

	}

	private function wasSeriesNotificationSent($alias, $seasonNumber, $seriesNumber){
		$this->wasSeriesNotificationSentQuery->execute(
			array(
				':alias'		=> $alias,
				':seasonNumber'	=> $seasonNumber,
				':seriesNumber'	=> $seriesNumber
			)
		);

		$res = $this->wasSeriesNotificationSentQuery->fetch();

		return intval($res[0]) > 0;
	}

	public function run(){
		try{
			$this->seriesParser->loadSrc(self::rssURL);
		}
		catch(HTTPException $ex){
			$this->tracer->logException('[HTTP ERROR]', __FILE__, __LINE__, $ex);
			return;
		}
		
		$latestSeriesList = $this->seriesParser->run();

		$this->pdo->query('
			LOCK TABLES
				series	WRITE,
				shows	READ;
		');
		
		foreach($latestSeriesList as $series){
			if($this->wasSeriesNotificationSent($series['alias'], $series['seasonNumber'], $series['seriesNumber'])){
				continue;
			}
			try{
				$this->seriesAboutParser->loadSrc($series['link']);
				$about = $this->seriesAboutParser->run();
				$this->addSeriesQuery->execute(
					array(
						':seasonNumber'	=> $series['seasonNumber'],
						':seriesNumber'	=> $series['seriesNumber'],
						':title_ru'		=> $about['title_ru'],
						':title_en'		=> $about['title_en'],
						':alias'		=> $series['alias']
					)
				);
			}
			catch(PDOException $ex){
				$this->tracer->logException('[DB ERROR]', __FILE__, __LINE__, $ex);
					
				switch($ex->getCode()){
					case '02000':
						$this->tracer->logError('[WARNING]', __FILE__, __LINE__, 'Show wasn\'t found');
						break;
					
					default:
						$this->tracer->logError('[ERROR]', __FILE__, __LINE__, 'Unknown error code: '.$ex->getCode().PHP_EOL.$ex->getMessage().PHP_EOL);
						break;
				}
				

				$this->tracer->logEvent('[NEW SERIES]', __FILE__, __LINE__, PHP_EOL.print_r($series, true));
			}
			catch(SeriesIsNotPublishedYet $ex){
				continue;
			}
			catch(Exception $ex){
				$this->tracer->logException('[UNKNOWN ERROR]', __FILE__, __LINE__, $ex);
			}
		}

		$this->pdo->query('UNLOCK TABLES');
	}
}


$requester = new HTTPRequester();
$parser = new SeriesParser($requester);
$seriesAboutParser = new SeriesAboutParser($requester);
$SPE = new SeriesParserExecutor($parser, $seriesAboutParser);
$SPE->run();














