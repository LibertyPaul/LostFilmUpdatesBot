<?php

namespace parser;

require_once(__DIR__.'/Parser.php');
require_once(__DIR__.'/../lib/Tracer/Tracer.php');

class SeriesParser extends Parser{
	private $tracer;
	protected $rssData;
	
	public function __construct(\HTTPRequester\HTTPRequesterInterface $requester){
		parent::__construct($requester, null);

		$this->tracer = new \Tracer(__CLASS__);
	}

	public function loadSrc($path, $requestHeaders = array()){
		parent::loadSrc($path, $requestHeaders);

		# Sometimes LF produces invalid XML: ' & ' instead of ' &amp; '
		$pos = strpos($this->pageSrc, ' & ');
		if ($pos !== false){
			$this->tracer->logfWarning(
				'[RSS]', __FILE__, __LINE__,
				'Invalid XML token was found as pos. %d (first of, there may be more entries)',
				$pos
			);

			$this->pageSrc = str_replace(' & ', ' &amp; ', $this->pageSrc);
		}

		try{
			$this->rssData = new \SimpleXMLElement($this->pageSrc);
		}
		catch(\RuntimeException $ex){
			$this->tracer->logException('[XML ERROR]', __FILE__, __LINE__, $ex);
			$this->tracer->logError('[XML ERROR]', __FILE__, __LINE__, PHP_EOL.$this->pageSrc);
			throw $ex;
		}
	}

	private static function isUsualSeriesLink($URL){
		if(strpos($URL, '/additional/') !== false){
			return false;
		}

		if(strpos($URL, 'details.php') !== false){
			return false;
		}

		return true;
	}
	
	private function parseURL($URL){
		$regexp = '/https:\/\/[\w\.]*?lostfilm\.[^\/]+(\/[^\/]+)?\/series\/([^\/]+)\/season_(\d+)\/episode_(\d+)\//';
		$matches = array();
		$matchesRes = preg_match($regexp, $URL, $matches);
		if($matchesRes === false){
			$this->tracer->logfError(
				'[ERROR]', __FILE__, __LINE__,
				'preg_match has failed with code: [%s]'.PHP_EOL.
				'Link: [%s]',
				preg_last_error(),
				$URL
			);

			throw new \RuntimeException('Unable to parse URL [$URL]');
		}

		if($matchesRes === 0){
			$this->tracer->logError(
				'[DATA ERROR]', __FILE__, __LINE__,
				"Link '$URL' doesn't match pattern"
			);

			throw new \RuntimeException("Link doesn't match pattern");
		}

		assert($matchesRes === 1);

		return array(
			'URL'			=> $matches[0],
			'alias'			=> $matches[2],
			'seasonNumber'	=> $matches[3],
			'seriesNumber'	=> $matches[4]
		);
	}
	
	public function run(){
		assert($this->pageSrc !== null);

		$result = array(); // [URL, showAlias, seasonNumber, seriesNumber]
		
		foreach($this->rssData->channel->item as $item){
			if(self::isUsualSeriesLink($item->link) === false){
				continue;
			}

			try{
				$result[] = $this->parseURL($item->link);
			}
			catch(\Throwable $ex){
				$this->tracer->logException('[PARSE ERROR]', __FILE__, __LINE__, $ex);
				$this->tracer->logError(
					'[PARSE ERROR]', __FILE__, __LINE__,
					PHP_EOL.print_r($item, true)
				);
			}
		}
		
		return $result;
	}
}















