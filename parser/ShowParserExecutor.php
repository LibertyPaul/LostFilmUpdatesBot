<?php

namespace parser;

require_once(__DIR__.'/../lib/ErrorHandler.php');
require_once(__DIR__.'/../lib/ExceptionHandler.php');

require_once(__DIR__.'/ShowListFetcher.php');
require_once(__DIR__.'/../lib/HTTPRequester/HTTPRequester.php');

require_once(__DIR__.'/ParserPDO.php');
require_once(__DIR__.'/../lib/Tracer/Tracer.php');

require_once(__DIR__.'/../core/Show.php');


class ShowParserExecutor{
	private $showListFetcher;
	private $pdo;
	private $renewShowQuery;

	public function __construct(ShowListFetcher $showListFetcher){
		assert($showListFetcher !== null);
		$this->showListFetcher = $showListFetcher;
		$this->tracer = new \Tracer(__CLASS__);
		$this->pdo = ParserPDO::getInstance();
	}

	private static function isOnAir($status){
		assert(is_int($status));
		
		if($status !== 5){
			return 'Y';
		}
		else{
			return 'N';
		}
	}

	private function remapShowList($showList){
		$result = array();
	
		foreach($showList as $showInfo){
			$show = new \core\Show(
				$showInfo['alias'],
				$showInfo['title'],
				$showInfo['title_orig'],
				self::isOnAir(intval($showInfo['status']))
			);

			assert(array_key_exists($showInfo['alias'], $result) === false);
			$result[$showInfo['alias']] = $show;
		}

		return $result;
	}

	private function getCurrentAliases(){
		static $getCurrentAliasesQuery;
		if(isset($getCurrentAliasesQuery) === false){
			$getCurrentAliasesQuery = $this->pdo->prepare('SELECT `alias` FROM `shows`');
		}

		$getCurrentAliasesQuery->execute();
		return $getCurrentAliasesQuery->fetchAll(\PDO::FETCH_COLUMN, 0);
	}

	private function addShow(\core\Show $show){
		static $addShowQuery;
		if(isset($addShowQuery) === false){
			$addShowQuery = $this->pdo->prepare('
				INSERT INTO `shows` (
					alias,
					title_ru,
					title_en,
					onAir,
					firstAppearanceTime,
					lastAppearanceTime
				)
				VALUES (
					:alias,
					:title_ru,
					:title_en,
					:onAir,
					NOW(),
					NOW()
				)
			');
		}
		
		try{
			$addShowQuery->execute(
				array(
					':alias'	=> $show->getAlias(),
					':title_ru'	=> $show->getTitleRu(),
					':title_en'	=> $show->getTitleEn(),
					':onAir'	=> $show->getOnAir()
				)
			);
		}
		catch(\PDOException $ex){
			$this->tracer->logException('[DATABASE]', __FILE__, __LINE__, $ex);
			$this->tracer->logError('[DATABASE]', __FILE__, __LINE__, PHP_EOL.$show);
			throw $ex;
		}
	}

	private function renewShow(\core\Show $show){
		static $renewShowQuery;
		
		if(isset($renewShowQuery) === false){
			$renewShowQuery = $this->pdo->prepare('
				UPDATE `shows`
				SET	`title_ru`				= :title_ru,
					`title_en`				= :title_en,
					`onAir`					= :onAir,
					`lastAppearanceTime`	= NOW()
				WHERE `alias` = :alias
			');
		}

		try{
			$renewShowQuery->execute(
				array(
					':alias'	=> $show->getAlias(),
					':title_ru'	=> $show->getTitleRu(),
					':title_en'	=> $show->getTitleEn(),
					':onAir'	=> $show->getOnAir()
				)
			);
		}
		catch(\PDOException $ex){
			$this->tracer->logException('[DATABASE]', __FILE__, __LINE__, $ex);
			$this->tracer->logError('[DATABASE]', __FILE__, __LINE__, PHP_EOL.$show);
		}
	}

	public function run(){
		$parsedShowList = $this->showListFetcher->fetchShowList();
		$parsedShowList = $this->remapShowList($parsedShowList);
		$parsedAliasList = array_keys($parsedShowList);

		$this->pdo->query('LOCK TABLES `shows` WRITE');

		$currentAliasList = $this->getCurrentAliases();

		$newAliasList = array_diff($parsedAliasList, $currentAliasList);
		foreach($newAliasList as $newAlias){
			$this->tracer->logEvent(
				'[NEW SHOW]', __FILE__, __LINE__,
				PHP_EOL.$parsedShowList[$newAlias]
			);
			try{
				$this->addShow($parsedShowList[$newAlias]);
			}
			catch(\Throwable $ex){
				$delimiter_pre = "[";
				$delimiter_post = "]";
				$delimiter = $delimiter_post.$delimiter_pre;
				$this->tracer->logDebug(
					'[NEW SHOW]', __FILE__, __LINE__,
					"Falied to insert a show.".PHP_EOL.
					"My records:"	.$delimiter_pre.join($delimiter, $currentAliasList)	.$delimiter_post.PHP_EOL.
					"Site shows:"	.$delimiter_pre.join($delimiter, $parsedAliasList)	.$delimiter_post.PHP_EOL.
					"Diff:"			.$delimiter_pre.join($delimiter, $newAliasList)		.$delimiter_post.PHP_EOL
				);
			}
		}
		
		$sameAliasList = array_intersect($parsedAliasList, $currentAliasList);
		foreach($sameAliasList as $sameAlias){
			$this->renewShow($parsedShowList[$sameAlias]);
		}

		$outdatedAliasList = array_diff($currentAliasList, $parsedAliasList);
		foreach($outdatedAliasList as $outdatedAlias){
			$this->tracer->logWarning(
				'[OUTDATED SHOW]', __FILE__, __LINE__,
				"Show $outdatedAlias has become outdated"
			);
		}

		$this->pdo->query('UNLOCK TABLES');
	}
}

$requester = new \HTTPRequester();
$showListFetcher = new ShowListFetcher($requester);
$showParserExecutor = new ShowParserExecutor($showListFetcher);
$showParserExecutor->run();

