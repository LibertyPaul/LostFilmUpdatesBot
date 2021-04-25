<?php

namespace parser;

require_once(__DIR__.'/Parser.php');
require_once(__DIR__.'/../lib/Tracer/TracerFactory.php');

class SeriesParser extends Parser{
	private $tracer;
	protected $rssData;
	
	public function __construct(
		\HTTPRequester\HTTPRequesterInterface $requester,
		\PDO $pdo
	){
		parent::__construct($requester, null);

		$this->tracer = \TracerFactory::getTracer(__CLASS__, $pdo);
	}

	public function loadSrc(string $URL, array $requestHeaders = array()){
		parent::loadSrc($URL, $requestHeaders);

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

		$this->rssData = new \SimpleXMLElement($this->pageSrc);
	}

	private static function isUsualSeriesLink(?string $URL){
		if(strpos($URL, '/additional/') !== false){
			return false;
		}

		if(strpos($URL, 'details.php') !== false){
			return false;
		}

		return true;
	}
	
	private function parseURL(?string $URL){
		$anything = '[^\/]+';

		$urlStructure = array(
			'1: protocol'	=> "https:\/\/",		# https://
			'2: domain'		=> "$anything\/",		# www.lostfilmtv.site/
			'3: subDir'		=> "($anything\/)?",	# mr/
			'4: series'		=> "series\/",			# series/
			'5: showAlias'	=> "($anything)\/",		# City_on_a_Hill/
			'6: season'		=> "season_(\d+)\/",	# season_2/
			'7: episode'	=> "episode_(\d+)\/"	# episode_4/
		);

		$regexp = '/'.join('', $urlStructure).'/';

		$matches = array();
		$matchesRes = preg_match($regexp, $URL, $matches);
		if($matchesRes === false){
			$this->tracer->logfError(
				'[o]', __FILE__, __LINE__,
				'preg_match has failed with code: [%s]'.PHP_EOL.
				'Link: [%s]'.PHP_EOL.
				'Regex: [%s]',
				preg_last_error(),
				$URL,
				$regex
			);

			throw new \RuntimeException('Unable to parse URL [$URL]');
		}

		if($matchesRes === 0){
			$this->tracer->logError(
				'[o]', __FILE__, __LINE__,
				"Link doesn't match the pattern".PHP_EOL.
				'Link: [%s]'.PHP_EOL.
				'Regex: [%s]',
				$URL,
				$regex
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
				$this->tracer->logDebug(
					'[PARSE ERROR]', __FILE__, __LINE__,
					PHP_EOL.print_r($item, true)
				);
			}
		}
		
		return $result;
	}
}















