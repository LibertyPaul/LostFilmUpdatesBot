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

require_once(__DIR__.'/../lib/DAL/Series/Series.php');
require_once(__DIR__.'/../lib/DAL/Series/SeriesAccess.php');
require_once(__DIR__.'/../lib/DAL/Shows/ShowsAccess.php');

class SeriesParserExecutor{
	private $config;
	private $seriesParser;
	private $seriesAboutsParser;
	private $maxShowNotReadyPeriod;
	private $seriesAccess;
	private $showsAccess;
	private $tracer;
	
	public function __construct(Parser $seriesParser, Parser $seriesAboutParser){
		$this->seriesParser = $seriesParser;
		$this->seriesAboutParser = $seriesAboutParser;

		$this->tracer = new \Tracer(__CLASS__);
		
		$pdo = ParserPDO::getInstance();
		$this->config = new \Config($pdo);
		$maxShowNotReadyPeriodMins = $this->config->getValue('Parser', 'Max Show Not Ready Period Mins', 30);
		$this->maxShowNotReadyPeriod = new \DateInterval('PT'.$maxShowNotReadyPeriodMins.'M');

		$this->seriesAccess = new \DAL\SeriesAccess($pdo);
		$this->showsAccess = new \DAL\ShowsAccess($pdo);
	}

	private function processSeries(array $seriesMetaInfo){
		$DBSeries = $this->seriesAccess->getSeriesByAliasSeasonSeries(
			$seriesMetaInfo['alias'],
			intval($seriesMetaInfo['seasonNumber']),
			intval($seriesMetaInfo['seriesNumber'])
		);

		if($DBSeries !== null && $DBSeries->isReady()){
			return;
		}

		$this->seriesAboutParser->loadSrc($seriesMetaInfo['URL']);
		$seriesInfo = $this->seriesAboutParser->run();

		$show = $this->showsAccess->getShowByAlias($seriesMetaInfo['alias']);
		if($show === null){
			$this->tracer->logfError(
				'[o]', __FILE__, __LINE__,
				'An episode of non-existing show was found.'.PHP_EOL.'%s',
				print_r($seriesMetaInfo, true)
			);

			return;
		}

		$LFSeries = new \DAL\Series(
			null,
			new \DateTimeImmutable(),
			$show->getId(),
			intval($seriesMetaInfo['seasonNumber']),
			intval($seriesMetaInfo['seriesNumber']),
			$seriesInfo['title_ru'],
			$seriesInfo['title_en'],
			$seriesInfo['status'] === SeriesStatus::Ready
		);

		$episodeDescription = sprintf(
			"%s S%02dE%02d %s(%s) [%s]",
			$show->getAlias(),
			$LFSeries->getSeasonNumber(),
			$LFSeries->getSeriesNumber(),
			$LFSeries->getTitleRu(),
			$LFSeries->getTitleEn(),
			$LFSeries->isReady() ? 'Ready' : sprintf('Not ready (%s)', $seriesInfo['why'])
		);

		if($DBSeries === null){
			$this->tracer->logfEvent('[o]', __FILE__, __LINE__, "New episode: %s", $episodeDescription);
			$this->seriesAccess->addSeries($LFSeries);
		}
		else{
			$LFSeries->setId($DBSeries->getId());
			$this->tracer->logfEvent('[o]', __FILE__, __LINE__, "Existing episode: %s", $episodeDescription);
			
			if($LFSeries->isReady() === false){
				$now = new \DateTimeImmutable();
				$diff = $DBSeries->getFirstSeenAt()->diff($now);

				if($diff > $this->maxShowNotReadyPeriod){
					$this->tracer->logfWarning(
						'[o]', __FILE__, __LINE__,
						'Episode was unready for too long. Overriding & marking as ready.'
					);

					$LFSeries->setReady();
				}
			}
			
			if($LFSeries->isReady()){
				$this->seriesAccess->updateSeries($LFSeries);
				$this->tracer->logDebug('[o]', __FILE__, __LINE__, 'Marked as ready.');
			}
		}
	}

	public function run(){
		$rssURL = $this->config->getValue('Parser', 'RSS URL', 'https://www.lostfilm.tv/rss.xml');
		$customHeaders = array();

		$customHeader = $this->config->getValue('Parser', 'RSS Custom Header', null);
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

		
		$this->seriesAccess->lockSeriesWriteShowsRead();
		
		foreach($latestSeriesList as $seriesMetaInfo){
			try{
				$this->processSeries($seriesMetaInfo);
			}
			catch(\Throwable $ex){
				$this->tracer->logException('[o]', __FILE__, __LINE__, $ex);
				$this->tracer->logDebug(
					'[NEW SERIES]', __FILE__, __LINE__,
					PHP_EOL.print_r($seriesMetaInfo, true)
				);
			}
		}

		$this->seriesAccess->unlockTables();
	}
}


$requester = new \HTTPRequester\HTTPRequester();
$parser = new SeriesParser($requester);
$seriesAboutParser = new SeriesAboutParser($requester);
$seriesParserExecutor = new SeriesParserExecutor($parser, $seriesAboutParser);
$seriesParserExecutor->run();



