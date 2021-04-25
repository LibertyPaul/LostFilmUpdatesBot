<?php

namespace parser;

require_once(__DIR__.'/../lib/Tracer/TracerFactory.php');
require_once(__DIR__.'/../lib/HTTPRequester/HTTPRequesterInterface.php');
require_once(__DIR__.'/../lib/Config.php');
require_once(__DIR__.'/../lib/DAL/Shows/Show.php');

class ShowListFetcher{
	private $requester;
	private $tracer;
	private $config;
	
	public function __construct(
		\HTTPRequester\HTTPRequesterInterface $requester,
		\Config $config,
		\PDO $pdo
	){
		$this->requester = $requester;
		$this->config = $config;

		$this->tracer = \TracerFactory::getTracer(__CLASS__, $pdo);
	}

	private static function isOnAir(int $status){
		return $status !== 5;
	}

	public function fetchShowList(){
		$URL = $this->config->getValue('Parser', 'ShowListURL', 'https://www.lostfilm.tv/ajaxik.php');
		
		$customHeader = $this->config->getValue('Parser', 'ShowList Custom Header');
		$customHeaders = array();
		if ($customHeader !== null){
			$customHeaders[] = $customHeader;
		}

		$showList = array();

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
				$this->tracer->logException(__FILE__, __LINE__, $ex);
				throw $ex;
			}

			$shows = json_decode($showsJSON, true);
			if($shows === null){
				$this->tracer->logfError(
                    __FILE__, __LINE__,
                    'json_decode error: [%s]',
                    json_last_error_msg()
				);

				$this->tracer->logDebug(
                    __FILE__, __LINE__,
                    "Erroneous JSON:" . PHP_EOL .
                    $showsJSON
				);

				throw new \RuntimeException('json_decode error: '.json_last_error_msg());
			}

			foreach($shows['data'] as $showInfo){
				$showInfo['alias'] = trim($showInfo['alias']);

				if(empty($showInfo['alias'])){
					$this->tracer->logWarning(
                        __FILE__, __LINE__,
                        'Alias is empty:' . PHP_EOL .
                        print_r($showInfo, true)
					);

					continue;
				}

				$show = new \DAL\Show(
					null,
					$showInfo['alias'],
					$showInfo['title'],
					$showInfo['title_orig'],
					self::isOnAir(intval($showInfo['status'])),
					new \DateTimeImmutable(),
					new \DateTimeImmutable()
				);

				$showList[$showInfo['alias']] = $show;
			}

			$pos += count($shows['data']);
		}while(count($shows['data']) > 0);

		return $showList;
	}
}

