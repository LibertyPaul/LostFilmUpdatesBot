<?php

namespace parser;

require_once(__DIR__.'/../lib/Tracer/Tracer.php');
require_once(__DIR__.'/../lib/HTTPRequester/HTTPRequesterInterface.php');
require_once(__DIR__.'/../lib/Config.php');

class ShowListFetcher{
	private $requester;
	private $tracer;
	private $config;
	
	public function __construct(\HTTPRequester\HTTPRequesterInterface $requester, \Config $config){
		$this->requester = $requester;
		$this->config = $config;

		$this->tracer = new \Tracer(__CLASS__);
	}

	public function fetchShowList(){
		$URL = $config->getValue('Parser', 'ShowListURL', 'https://www.lostfilm.tv/ajaxik.php');
		
		$customHeader = $config->getValue('Parser', 'ShowList Custom Header', null);
		$customHeaders = array();
		if ($customHeader !== null){
			$customHeaders[] = $customHeader;
		}

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
				$requestProperties = new \HTTPRequester\HTTPRequestProperties(
					\HTTPRequester\RequestType::Get,
					\HTTPRequester\ContentType::TextHTML,
					$URL,
					$args,
					$customHeaders
				);

				$res = $this->requester->request($requestProperties);
				$showsJSON = $res->getBody();
			}
			catch(\Throwable $ex){
				$this->tracer->logException('[HTTP LIB]', __FILE__, __LINE__, $ex);
				throw $ex;
			}

			$shows = json_decode($showsJSON, true);
			if($shows === false){
				$this->tracer->logError(
					'[JSON ERROR]', __FILE__, __LINE__,
					'json_decode error: '.json_last_error_msg().PHP_EOL.
					$showsJSON
				);

				throw new \RuntimeException('json_decode error: '.json_last_error_msg());
			}

			if(is_array($shows['data']) === false){
				$this->tracer->logError(
					'[DATA ERROR]', __FILE__, __LINE__,
					'Incorrect show info'.PHP_EOL.
					print_r($shows, true)
				);

				throw new \RuntimeException('Incorrect show info: data element is not found');
			}

			foreach($shows['data'] as $show){
				$show['alias'] = trim($show['alias']);

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

			$pos += count($shows['data']);
		}while(count($shows['data']) > 0);

		return $showInfoList;
	}
}

