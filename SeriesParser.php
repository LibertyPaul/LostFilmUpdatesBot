<?php
require_once(__DIR__.'/config/stuff.php');
require_once(__DIR__.'/Parser.php');
require_once(__DIR__.'/Exceptions/StdoutTextException.php');
require_once(__DIR__.'/Notifier.php');
require_once(__DIR__.'/TelegramBotFactory.php');


class FullSeasonWasFoundException extends Exception{}


class SeriesParser extends Parser{ //TODO: move series updater part to another class.
	protected $rssData;
	protected $notifier;
	
	protected $getUrlIdQuery;
	protected $getSeriesNumberQuery;
	protected $setLatestSeriesNumberQuery;

	public function __construct(){
		parent::__construct(null);
		$this->notifier = new Notifier(new TelegramBotFactory());
		
		$pdo = createPDO();
		
		$this->setLatestSeriesNumberQuery = $pdo->prepare('
			UPDATE `shows`
			SET
				`seasonNumber` = :seasonNumber,
				`seriesNumber` = :seriesNumber
			WHERE `id` = :show_id
		');
		
		$this->getSeriesNumberQuery = $pdo->prepare('
			SELECT `seasonNumber`, `seriesNumber`
			FROM `shows`
			WHERE `id` = :show_id
		');
		
		
		$this->getUrlIdQuery = $pdo->prepare('
			SELECT `id`
			FROM `shows`
			WHERE 	STRCMP(`title_ru`, :title_ru) = 0
			AND		STRCMP(`title_en`, :title_en) = 0
		');
	}

	public function loadSrc($path){
		parent::loadSrc($path);
		$this->rssData = new SimpleXMLElement($this->pageSrc);
	}
			
		
	protected function getShowId($showTitleRu, $showTitleEn){		
		$this->getUrlIdQuery->execute(
			array(
				':title_ru' => $showTitleRu,
				':title_en' => $showTitleEn
			)
		);
		
		$res = $this->getUrlIdQuery->fetchAll();
		if(count($res) === 0){
			throw new StdoutTextException("Show $showTitleRu ($showTitleEn) was not found in database");
		}
		
		return $res[0]['id'];
	}
	
	protected function isNewSeries($show_id, $seasonNumber, $seriesNumber){		
		$this->getSeriesNumberQuery->execute(
			array(
				':show_id' => $show_id
			)
		);
		
		$res = $this->getSeriesNumberQuery->fetchAll();
		if(count($res) === 0){
			throw new Exception("show_id was not found");
		}
		
		if(isset($res[0]['seasonNumber'], $res[0]['seriesNumber']) === false){
			return true;
		}
		
		$loggedSeasonNumber = intval($res[0]['seasonNumber']);
		$loggedSeriesNumber = intval($res[0]['seriesNumber']);
		
		echo "Logged: S$loggedSeasonNumber E$loggedSeriesNumber".PHP_EOL;
		echo "New:\t S$seasonNumber E$seriesNumber".PHP_EOL;
		
		return $seasonNumber > $loggedSeasonNumber || $seasonNumber === $loggedSeasonNumber && $seriesNumber > $loggedSeriesNumber;
	}
	
	protected function submitNewSeries($show_id, $seriesNameRu, $seriesNameEn, $seasonNumber, $seriesNumber){
		$this->setLatestSeriesNumberQuery->execute(
			array(
				':seasonNumber' => $seasonNumber,
				':seriesNumber' => $seriesNumber,
				':show_id'		=> $show_id
			)
		);
		
		$this->notifier->newSeriesEvent($show_id, $seasonNumber, $seriesNumber, $seriesNameRu);
	}
	
	protected function parseTitle($title){
		$lastDotPos = strrpos($title, '.');
		$seasonSeriesNumberTag = substr($title, $lastDotPos + 1);
		$seasonSeriesNumberTag = trim($seasonSeriesNumberTag);
		
		$matches = array();
		$res = preg_match('/S0?(\d+)E0?(\d+)/', $seasonSeriesNumberTag, $matches);
		if($res !== 1){
			$res = preg_match('/S\d+/', $seasonSeriesNumberTag, $matches);

			if($res === 1){
				throw new FullSeasonWasFoundException();
			}

			throw new StdoutTextException('seasonSeriesNumberTag parsing error `'.$seasonSeriesNumberTag.'`');
		}
		
		$seasonNumber = $matches[1];
		$seriesNumber = $matches[2];
		

		$title = substr($title, 0, $lastDotPos);
		$formatEndPos = strrpos($title, ']');
		
		$formatTag = null;
		if($formatEndPos !== false){
			$formatStartPos = findMatchingParenthesis($title, $formatEndPos);
			if($formatStartPos === false){
				throw new StdoutTextException('Broken format tag (opening parenthesis not found)');
			}

			$length = $formatEndPos - $formatStartPos - 1;
			$formatTag = substr($title, $formatStartPos + 1, $length);
			$title = substr($title, 0, $formatStartPos);
			$title = rtrim($title);
		}
		
		$seriesNameEn = null;
		$seriesNameEnEndPos = strlen($title) - 1;
		if($title[$seriesNameEnEndPos] === ')'){
			$seriesNameEnStartPos = findMatchingParenthesis($title, $seriesNameEnEndPos);
			if($seriesNameEnStartPos === false){
				throw new StdoutTextException("Broken series name (opening parenthesis not found)");
			}

			$length = $seriesNameEnEndPos - $seriesNameEnStartPos - 1;
			$seriesNameEn = substr($title, $seriesNameEnStartPos + 1, $length);
			$title = substr($title, 0, $seriesNameEnStartPos);
		}
		
		$seriesNameRu = null;
		$offset = 2;
		
		$seriesNameRuStartPos = strpos($title, ').');
		if($seriesNameRuStartPos === false){
			$seriesNameRuStartPos = strpos($title, '.');
			$offset = 1;
			if($seriesNameRuStartPos === false){
				throw new StdoutTextException("Start of show ru name wasn't found");
			}
		}
			
		$seriesNameRu = substr($title, $seriesNameRuStartPos + $offset);
		$seriesNameRu = trim($seriesNameRu);
		$title = substr($title, 0, $seriesNameRuStartPos + 1); // + 1 to left closing parenthesis
	
	
		$showNameEn = null;
	
		$showNameEnEndPos = strrpos($title, ')');
		if($showNameEnEndPos !== false){
			$showNameEnStartPos = findMatchingParenthesis($title, $showNameEnEndPos);
			if($showNameEnStartPos === false){
				throw new StdoutTextException('Broken show name (opening parenthesis not found)');
			}
			
			$length = $showNameEnEndPos - $showNameEnStartPos - 1;
			$showNameEn = substr($title, $showNameEnStartPos + 1, $length);
			$title = substr($title, 0, $showNameEnStartPos);
		}
		
		$title = trim($title);
		$showNameRu = $title;
		
		return array(
			'showTitleRu'	=> $showNameRu,
			'showTitleEn' 	=> $showNameEn,
			'seriesTitleRu' => $seriesNameRu,
			'seriesTitleEn' => $seriesNameEn,
			'seasonNumber' 	=> intval($seasonNumber),
			'seriesNumber' 	=> intval($seriesNumber)
		);
	}
		
	
	public function run(){
		foreach($this->rssData->channel->item as $item){
			try{
				$result = $parsedTitle = $this->parseTitle($item->title);
				$showId = $this->getShowId($result['showTitleRu'], $result['showTitleEn']);
				
				if($this->isNewSeries($showId, $result['seasonNumber'], $result['seriesNumber'])){
					echo "New series: show_id: $showId, S$result[seasonNumber]E$result[seriesNumber]\t$result[seriesTitleRu] ($result[seriesTitleEn])".PHP_EOL;
					$this->submitNewSeries($showId, $result['seriesTitleRu'], $result['seriesTitleEn'], $result['seasonNumber'], $result['seriesNumber']);
				}
			}
			catch(FullSeasonWasFoundException $ex){
				echo 'Full season entry was found. Skipping'.PHP_EOL;
			}
			catch(Exception $ex){
				echo "[ERROR]".$ex->getMessage().PHP_EOL;
			}
		}
	}
}















