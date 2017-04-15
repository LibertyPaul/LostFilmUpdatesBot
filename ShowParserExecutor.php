<?php
require_once(__DIR__.'/ErrorHandler.php');
require_once(__DIR__.'/ExceptionHandler.php');

require_once(__DIR__.'/ShowListFetcher.php');
require_once(__DIR__.'/HTTPRequester.php');

require_once(__DIR__.'/ParserPDO.php');
require_once(__DIR__.'/Tracer.php');


class ShowParserExecutor{
	private $showListFetcher;

	private $pdo;
	private $getCurrentAliasList;
	private $addShowQuery;
	private $renewShowQuery;

	public function __construct(ShowListFetcher $showListFetcher){
		assert($showListFetcher !== null);
		$this->showListFetcher = $showListFetcher;

		$this->tracer = new Tracer(__CLASS__);

		$this->pdo = ParserPDO::getInstance();

		$this->getCurrentAliasList = $this->pdo->prepare('
			SELECT `alias` FROM `shows`
		');

		$this->addShowQuery = $this->pdo->prepare('
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

		$this->renewShowQuery = $this->pdo->prepare('
			UPDATE `shows`
			SET	`title_ru`				= :title_ru,
				`title_en`				= :title_en,
				`onAir`					= :onAir,
				`lastAppearanceTime`	= NOW()
			WHERE `alias` = :alias
		');
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
			$result[$showInfo['alias']] = array(
				'title_ru'	=> $showInfo['title'],
				'title_en'	=> $showInfo['title_orig'],
				'onAir'		=> self::isOnAir(intval($showInfo['status']))
			);
		}

		return $result;
	}

	private function getCurrentAliasList(){
		$this->getCurrentAliasList->execute();
		return $this->getCurrentAliasList->fetchAll(PDO::FETCH_COLUMN, 0);
	}

	public function run(){
		$parsedShowList = $this->showListFetcher->fetchShowList();
		$parsedShowList = $this->remapShowList($parsedShowList);
		$parsedAliasList = array_keys($parsedShowList);

		$this->pdo->query('LOCK TABLES `shows` WRITE');

		$currentAliasList = $this->getCurrentAliasList();

		$newAliasList = array_diff($parsedAliasList, $currentAliasList);
		foreach($newAliasList as $newAlias){
			try{
				$this->addShowQuery->execute(
					array(
						':alias'	=> $newAlias,
						':title_ru'	=> $parsedShowList[$newAlias]['title_ru'],
						':title_en'	=> $parsedShowList[$newAlias]['title_en'],
						':onAir'	=> $parsedShowList[$newAlias]['onAir']
					)
				);
			}
			catch(PDOException $ex){
				$this->tracer->logException('[DATABASE]', __FILE__, __LINE__, $ex);
				$this->tracer->logError('[DATABASE]', __FILE__, __LINE__, $newAlias);
				$this->tracer->logError('[DATABASE]', __FILE__, __LINE__, PHP_EOL.print_r($parsedShowList[$newAlias], true));
				continue;
			}
			$this->tracer->logEvent('[NEW SHOW]', __FILE__, __LINE__, PHP_EOL.print_r($parsedShowList[$newAlias], true));
		}
		
		$sameAliasList = array_intersect($parsedAliasList, $currentAliasList);
		foreach($sameAliasList as $sameAlias){
			try{
				$this->renewShowQuery->execute(
					array(
						':alias'	=> $sameAlias,
						':title_ru'	=> $parsedShowList[$sameAlias]['title_ru'],
						':title_en'	=> $parsedShowList[$sameAlias]['title_en'],
						':onAir'	=> $parsedShowList[$sameAlias]['onAir']
					)
				);
			}
			catch(PDOException $ex){
				$this->tracer->logException('[DATABASE]', __FILE__, __LINE__, $ex);
				$this->tracer->logError('[DATABASE]', __FILE__, __LINE__, $sameAlias);
				$this->tracer->logError('[DATABASE]', __FILE__, __LINE__, PHP_EOL.print_r($parsedShowList[$newAlias], true));
				continue;
			}
		}

		$outdatedAliasList = array_diff($currentAliasList, $parsedAliasList);
		foreach($outdatedAliasList as $outdatedAlias){
			$this->tracer->logEvent('[OUTDATED SHOW]', __FILE__, __LINE__, "Show $outdatedAlias has became outdated");
		}

		$this->pdo->query('UNLOCK TABLES');
	}
}

$requester = new HTTPRequester();
$showListFetcher = new ShowListFetcher($requester);
$showParserExecutor = new ShowParserExecutor($showListFetcher);
$showParserExecutor->run();

