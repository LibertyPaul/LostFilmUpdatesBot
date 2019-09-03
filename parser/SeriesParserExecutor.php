<?php

namespace parser;

require_once(__DIR__.'/../lib/ErrorHandler.php');
require_once(__DIR__.'/../lib/ExceptionHandler.php');

require_once(__DIR__.'/../lib/Config.php');
require_once(__DIR__.'/ParserPDO.php');
require_once(__DIR__.'/SeriesParser.php');
require_once(__DIR__.'/../lib/Tracer/Tracer.php');
require_once(__DIR__.'/SeriesAboutParser.php');
require_once(__DIR__.'/../lib/HTTPRequester/HTTPRequester.php');

class SeriesParserExecutor{
	private $pdo;
	private $config;
	private $seriesParser;
	private $seriesAboutsParser;
	private $getShowIdByAlias;
	private $addSeriesQuery;
	private $tracer;
	
	public function __construct(Parser $seriesParser, Parser $seriesAboutParser){
		$this->seriesParser = $seriesParser;
		$this->seriesAboutParser = $seriesAboutParser;

		$this->tracer = new \Tracer(__CLASS__);
		
		$this->pdo = ParserPDO::getInstance();
		$this->config = new \Config($this->pdo);

		$this->getShowIdByAlias = $this->pdo->prepare('
			SELECT `id` FROM `shows` WHERE `alias` = :alias
		');
		
		$this->addSeriesQuery = $this->pdo->prepare('
			INSERT INTO `series` (show_id, seasonNumber, seriesNumber, title_ru, title_en)
			VALUES :show_id, :seasonNumber, :seriesNumber, :title_ru, :title_en
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
		$rssURL = $this->config->getValue('Parser', 'RSS URL', 'https://www.lostfilm.tv/rss.xml');
		$customHeader = $this->config->getValue('Parser', 'RSS Custom Header', null);
		$customHeaders = array();
		if ($customHeader !== null){
			$customHeaders[] = $customHeader;
		}	

		try{
			$this->seriesParser->loadSrc($rssURL, $customHeaders);
		}
		catch(\Throwable $ex){
			$this->tracer->logException('[LF ERROR]', __FILE__, __LINE__, $ex);
			throw $ex;
		}
		
		$latestSeriesList = $this->seriesParser->run();

		$this->pdo->query('
			LOCK TABLES
				series	WRITE,
				shows	READ;
		');
		
		foreach($latestSeriesList as $series){
			if($this->wasSeriesNotificationSent(
				$series['alias'],
				$series['seasonNumber'],
				$series['seriesNumber'])
			){
				continue;
			}

			try{
				$this->seriesAboutParser->loadSrc($series['link']);
				$about = $this->seriesAboutParser->run();

				switch($about['status']){
					case SeriesStatus::Ready:
						$this->tracer->logEvent(
							'[o]', __FILE__, __LINE__,
							sprintf(
								"New series: %s S%02dE%02d %s(%s)",
								$series['alias'],
								$series['seasonNumber'],
								$series['seriesNumber'],	
                                $about['title_ru'],
                                $about['title_en']
							)
						);

						$this->getShowIdByAlias->execute(
							array(
								':alias' => $series['alias']
							)
						);

						$res = $this->getShowIdByAlias->fetch();
						if($res === false){
							throw new \RuntimeException("Show [$series[alias] was not found");
						}

						$show_id = $res[0];

						$this->addSeriesQuery->execute(
							array(
								':show_id'		=> $show_id,
								':seasonNumber'	=> $series['seasonNumber'],
								':seriesNumber'	=> $series['seriesNumber'],
								':title_ru'		=> $about['title_ru'],
								':title_en'		=> $about['title_en']
							)
						);

						$this->tracer->logDebug('[o]', __FILE__, __LINE__, 'Added.');

						break;

					case SeriesStatus::NotReady:
						$this->tracer->logDebug(
							'[o]', __FILE__, __LINE__,
							sprintf(
								'%s S%02dE%02d seems not to be ready yet (%s)',
								$series['alias'],
								$series['seasonNumber'],
								$series['seriesNumber'],	
								$about['why']
							)
						);
						continue;

					default:
						throw new \RuntimeException("Unknown status value: [$about[status]]");
				}
			}
			catch(\PDOException $ex){
				$this->tracer->logException('[DB ERROR]', __FILE__, __LINE__, $ex);
					
				switch($ex->getCode()){
					case '02000':
						$this->tracer->logError(
							'[WARNING]', __FILE__, __LINE__,
							'Show was not found'
						);
						break;
					
					default:
						$this->tracer->logError(
							'[ERROR]', __FILE__, __LINE__,
							'Unknown error code: '.$ex->getCode().PHP_EOL.
							$ex->getMessage()
						);
						break;
				}
				

				$this->tracer->logEvent(
					'[NEW SERIES]', __FILE__, __LINE__,
					PHP_EOL.print_r($series, true)
				);
			}
			catch(\Throwable $ex){
				$this->tracer->logException('[UNKNOWN ERROR]', __FILE__, __LINE__, $ex);
			}
		}

		$this->pdo->query('UNLOCK TABLES');
	}
}


$requester = new \HTTPRequester\HTTPRequester();
$parser = new SeriesParser($requester);
$seriesAboutParser = new SeriesAboutParser($requester);
$seriesParserExecutor = new SeriesParserExecutor($parser, $seriesAboutParser);
$seriesParserExecutor->run();














