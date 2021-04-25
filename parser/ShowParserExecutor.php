<?php

namespace parser;

require_once(__DIR__.'/../lib/ErrorHandler.php');
require_once(__DIR__.'/../lib/ExceptionHandler.php');

require_once(__DIR__.'/../lib/Config.php');
require_once(__DIR__.'/ShowListFetcher.php');
require_once(__DIR__.'/../lib/HTTPRequester/HTTPRequester.php');

require_once(__DIR__.'/ParserPDO.php');
require_once(__DIR__.'/../lib/Tracer/TracerFactory.php');

require_once(__DIR__.'/../lib/DAL/Shows/Show.php');
require_once(__DIR__.'/../lib/DAL/Shows/ShowsAccess.php');


class ShowParserExecutor{
	private $tracer;
	private $showListFetcher;
	private $showsAccess;

	public function __construct(\PDO $pdo, ShowListFetcher $showListFetcher){
		$this->showListFetcher = $showListFetcher;
		$this->tracer = \TracerFactory::getTracer(__CLASS__, $pdo);

		$this->showsAccess = new \DAL\ShowsAccess($pdo);
	}

	private static function aliasListToText(array $aliases){
		$delimiter_pre = "[";
		$delimiter_post = "]";
		$delimiter = $delimiter_post.$delimiter_pre;
		
		return $delimiter_pre.join($delimiter, $aliases).$delimiter_post;
	}

	private function handleNewShows(array $DBShowAliases, array $LFShows){
		$newShowAliases = array_diff(array_keys($LFShows), $DBShowAliases);

		foreach($newShowAliases as $newAlias){
			$this->tracer->logEvent(
				'[NEW SHOW]', __FILE__, __LINE__,
				PHP_EOL.$LFShows[$newAlias]
			);

			if($LFShows[$newAlias]->getTitleEn() === 'Dracula'){
				# LF has a conflicting title_en with newer Dracula BBC
				# Adding a hardcoded crotch to suppress the error
				# In case the problem repeats with another show, a proper solution to be implemented
				$this->tracer->logEvent(
					'[o]', __FILE__, __LINE__,
					'The old Dracula show was excluded to avoid collision'
				);

				continue;
			}

			try{
				$show_id = $this->showsAccess->addShow($LFShows[$newAlias]);
				$LFShows[$newAlias]->setId($show_id);
			}
			catch(\DAL\DuplicateValueException $ex){
				$this->tracer->logfError(
					'[o]', __FILE__, __LINE__,
					'Conflicting show title (%s): "%s". Failed to add.',
					$ex->getConstrainingIndexName(),
					$newAlias
				);
			}
			catch(\Throwable $ex){
				$this->tracer->logException('[DATABASE]', __FILE__, __LINE__, $ex);
				$this->tracer->logDebug(
					'[NEW SHOW]', __FILE__, __LINE__,
					"Falied to insert a show:".PHP_EOL.
					$LFShows[$newAlias].PHP_EOL.
					"My records: ".self::aliasListToText($DBShowAliases)
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
					"Unable to update show [$existingAlias]".PHP_EOL.$LFShows[$existingAlias]
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
$config = \Config::getConfig($pdo);
$showListFetcher = new ShowListFetcher($requester, $config, $pdo);
$showParserExecutor = new ShowParserExecutor($pdo, $showListFetcher);

$showParserExecutor->run();




