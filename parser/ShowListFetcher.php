<?php

namespace parser;

require_once(__DIR__.'/../lib/Tracer/Tracer.php');
require_once(__DIR__.'/../lib/HTTPRequester/HTTPRequesterInterface.php');

class ShowListFetcher{
	const URL = 'https://www.lostfilm.tv/ajaxik.php';

	private $requester;
	private $tracer;
	
	public function __construct(\HTTPRequesterInterface $requester, $pageEncoding = 'utf-8'){
		assert($requester !== null);
		$this->requester = $requester;
		$this->tracer = new \Tracer(__CLASS__);
	}

	public function fetchShowList(){
		$showInfoList = array();

		$args = array(
			'act' => 'serial',
			'type' => 'search',
			'o' => 0,
			's' => 3,
			't' => 0
		);

		$pos = 0;

		do{
			$args['o'] = $pos;

			try{
				$result = $this->requester->sendGETRequest(self::URL, $args);
			}
			catch(\HTTPException $ex){
				$this->tracer->logException('[HTTP]', __FILE__, __LINE__, $ex);
				throw $ex;
			}

			$result_json = $result['value'];

			$result = json_decode($result_json, true);
			if($result === false){
				$this->tracer->logError(
					'[JSON ERROR]', __FILE__, __LINE__,
					'json_decode error: '.json_last_error_msg().PHP_EOL.$result_json
				);

				throw new \RuntimeException('json_decode error: '.json_last_error_msg());
			}

			if(is_array($result['data']) === false){
				$this->tracer->logError(
					'[DATA ERROR]', __FILE__, __LINE__,
					'Incorrect show info'.PHP_EOL.
					print_r($result, true)
				);

				throw new \RuntimeException('Incorrect show info: data element is not found');
			}

			foreach($result as $show){
				if(empty($show['alias'])){
					$this->tracer->logWarning(
						'[DATA WARNING]', __FILE__, __LINE__,
						'Alias is empty:'.PHP_EOL.
						print_r($show, true)
					);

					continue;
				}

				$showInfoList[] = $show;
			}

			$pos += count($result);
		}while(count($result['data']) > 0);

		return $showInfoList;
	}
}

