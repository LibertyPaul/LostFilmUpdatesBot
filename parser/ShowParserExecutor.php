<?php

namespace parser;

require_once(__DIR__.'/../lib/ErrorHandler.php');
require_once(__DIR__.'/../lib/ExceptionHandler.php');

require_once(__DIR__.'/../lib/Config.php');
require_once(__DIR__.'/ShowListFetcher.php');
require_once(__DIR__.'/../lib/HTTPRequester/HTTPRequester.php');

require_once(__DIR__.'/ParserPDO.php');
require_once(__DIR__.'/../lib/Tracer/Tracer.php');

require_once(__DIR__.'/../lib/DAL/Shows/Show.php');
require_once(__DIR__.'/../lib/DAL/Shows/ShowsAccess.php');


class ShowParserExecutor{
	private $tracer;
	private $showListFetcher;
	private $showsAccess;

	public function __construct(\PDO $pdo, ShowListFetcher $showListFetcher){
		$this->showListFetcher = $showListFetcher;
		$this->tracer = new \Tracer(__CLASS__);

		$this->showsAccess = new \DAL\ShowsAccess($pdo);
	}

	private function handleNewShows(array $DBShowAliases, array $LFShows){
		$newShowAliases = array_diff(array_keys($LFShows), $DBShowAliases);

		foreach($newShowAliases as $newAlias){
			$this->tracer->logEvent(
				'[NEW SHOW]', __FILE__, __LINE__,
				PHP_EOL.$LFShows[$newAlias]
			);

			try{
				$show_id = $this->showsAccess->addShow($LFShows[$newAlias]);
				$LFShows[$newAlias]->setId($show_id);
			}
			catch(\Throwable $ex){
				$this->tracer->logException('[DATABASE]', __FILE__, __LINE__, $ex);
				$delimiter_pre = "[";
				$delimiter_post = "]";
				$delimiter = $delimiter_post.$delimiter_pre;
				$this->tracer->logDebug(
					'[NEW SHOW]', __FILE__, __LINE__,
					"Falied to insert a show [".$LFShows[$newAlias]."].".PHP_EOL.
					"My records:"	.$delimiter_pre.join($delimiter, $DBShowAliases)	.$delimiter_post.PHP_EOL.
					"Site shows:"	.$delimiter_pre.join($delimiter, $LFShowAliases)	.$delimiter_post.PHP_EOL.
					"Diff:"			.$delimiter_pre.join($delimiter, $newShowAliases)	.$delimiter_post.PHP_EOL
				);
			}
		}
	}

	private function handleExistingShows(array $DBShowAliases, array $LFShows){
		$existingShowAliases = array_intersect(array_keys($LFShows), $DBShowAliases);

		foreach($existingShowAliases as $existingAlias){
			try{
				$this->showsAccess->updateShow($LFShows[$existingAlias]);
			}
			catch(\PDOException $ex){
				$this->tracer->logError(
					'[DATABASE]', __FILE__, __LINE__,
					"Unable to update show [$existingAlias]".PHP_EOL.$show
				);

				$this->tracer->logException('[DATABASE]', __FILE__, __LINE__, $ex);
			}
		}
	}

	private function handleObsoleteShows(array $DBShowAliases, array $LFShows){
		$obsoleteShowAliases = array_diff($DBShowAliases, array_keys($LFShows));

		foreach($obsoleteShowAliases as $obsoleteAlias){
			$this->tracer->logWarning(
				'[OUTDATED SHOW]', __FILE__, __LINE__,
				"Show $obsoleteAlias does not exist at LostFilm anymore."
			);
		}
	}

	public function run(){
		$LFShows = $this->showListFetcher->fetchShowList();
		
		try{
			$this->showsAccess->lockShowsWrite();
			$DBShowAliases = $this->showsAccess->getAliases();

			$this->handleNewShows($DBShowAliases, $LFShows);
			$this->handleExistingShows($DBShowAliases, $LFShows);
			$this->handleObsoleteShows($DBShowAliases, $LFShows);
		}
		catch(\Throwable $ex){
			$this->tracer->logException('[o]', __FILE__, __LINE__, $ex);
		}
		finally{
			$this->showsAccess->unlockTables();
		}
	}
}

$requester = new \HTTPRequester\HTTPRequester();

$pdo = ParserPDO::getInstance();
$config = new \Config($pdo);
$showListFetcher = new ShowListFetcher($requester, $config);
$showParserExecutor = new ShowParserExecutor($pdo, $showListFetcher);

$showParserExecutor->run();




