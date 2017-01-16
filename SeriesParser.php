<?php
require_once(__DIR__.'/config/stuff.php');
require_once(__DIR__.'/Parser.php');
require_once(__DIR__.'/Exceptions/StdoutTextException.php');
require_once(__DIR__.'/Notifier.php');
require_once(__DIR__.'/TelegramBotFactory.php');
require_once(__DIR__.'/EchoTracer.php');


class FullSeasonWasFoundException extends Exception{}


class SeriesParser extends Parser{
	private $tracer;
	protected $rssData;
	protected $notifier;
	
	protected $getUrlIdQuery;
	protected $addSeries;
	protected $latestSeriesQuery;

	public function __construct(HTTPRequesterInterface $requester){
		parent::__construct($requester, null);

		$this->tracer = new EchoTracer(__CLASS__);
	}

	public function loadSrc($path){
		parent::loadSrc($path);
		$this->rssData = new SimpleXMLElement($this->pageSrc);
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
		$result = array(); // [seriesTitleRu, seriesTitleEn, seasonNumber, seriesNumber]
		
		foreach($this->rssData->channel->item as $item){
			try{
				$result[] = $this->parseTitle($item->title);
			}
			catch(FullSeasonWasFoundException $ex){
				// mmmk, skipping
			}
			catch(Exception $ex){
				$this->tracer->logException('[PARSE ERROR]', $ex);
			}
		}
		
		return $result;
	}
}















