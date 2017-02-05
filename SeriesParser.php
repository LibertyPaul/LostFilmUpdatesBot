<?php
require_once(__DIR__.'/Parser.php');
require_once(__DIR__.'/Tracer.php');

class FullSeasonWasFoundException extends Exception{}


class SeriesParser extends Parser{
	private $tracer;
	protected $rssData;
	
	public function __construct(HTTPRequesterInterface $requester){
		parent::__construct($requester, null);

		$this->tracer = new Tracer(__CLASS__);
	}

	public function loadSrc($path){
		parent::loadSrc($path);
		try{
			$this->rssData = new SimpleXMLElement($this->pageSrc);
		}
		catch(Exception $ex){
			$this->tracer->logException('[XML ERROR]', $ex);
			$this->tracer->log('[XML ERROR]', __FILE__, __LINE__, PHP_EOL.$this->pageSrc);
			throw $ex;
		}
	}
	
	private function parseLink($link){
		$regexp = '/https:\/\/[\w\.]*?lostfilm.tv\/series\/([^\/]+)\/season_(\d+)\/episode_(\d+)\//';
		$matches = array();
		$matchesRes = preg_match($regexp, $link, $matches);
		if($matchesRes === false){
			$this->tracer->log('[ERROR]', __FILE__, __LINE__, 'preg_match has failed with code: '.preg_last_error());
			$this->tracer->log('[ERROR]', __FILE__, __LINE__, "Link: '$link'");
			throw new Exception('preg_match has failed');
		}

		if($matchesRes === 0){
			$this->tracer->log('[DATA ERROR]', __FILE__, __LINE__, "Link '$link' doesn't match pattern");
			throw new Exception("Link doesn't match pattern");
		}

		assert($matchesRes === 1);

		return array(
			'link'			=> $matches[0],
			'showAlias'		=> $matches[1],
			'seasonNumber'	=> $matches[2],
			'seriesNumber'	=> $matches[3]
		);
	}
		
	
	public function run(){
		$result = array(); // [link, showAlias, seasonNumber, seriesNumber]
		
		foreach($this->rssData->channel->item as $item){
			try{
				$result[] = $this->parseLink($item->link);
			}
			catch(FullSeasonWasFoundException $ex){
				// mmmk, skipping
			}
			catch(Exception $ex){
				$this->tracer->logException('[PARSE ERROR]', $ex);
				$this->tracer->log('[PARSE ERROR]', __FILE__, __LINE__, PHP_EOL.print_r($item, true));
			}
		}
		
		return $result;
	}
}















