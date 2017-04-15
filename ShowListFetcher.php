<?php
require_once(__DIR__.'/Tracer.php');
require_once(__DIR__.'/HTTPRequesterInterface.php');

class ShowListFetcher{
	private $requester;
	private $tracer;
	
	const showInfoTemplate	= 'https://www.lostfilm.tv/ajaxik.php?act=serial&type=search&o=#FROM&s=3&t=0';
	const showInfoStep		= 10;

	public function __construct(HTTPRequesterInterface $requester, $pageEncoding = 'utf-8'){
		assert($requester !== null);
		$this->requester = $requester;

		$this->tracer = new Tracer(__CLASS__);
	}
	
	private static function getShowsInfoURL($from){
		assert(is_int($from));
		return str_replace('#FROM', $from, self::showInfoTemplate);
	}

	public function fetchShowList(){
		$showInfoList = array();
		$pos = 0;

		do{
			$url = self::getShowsInfoURL($pos);
			try{
				$result = $this->requester->sendGETRequest($url);
			}
			catch(HTTPException $ex){
				$this->tracer->logException('[HTTP]', __FILE__, __LINE__, $ex);
				throw $ex;
			}

			$result_json = $result['value'];

			$result = json_decode($result_json, true);
			if($result === false){
				$this->tracer->logError('[JSON ERROR]', __FILE__, __LINE__, 'json_decode error: '.json_last_error_msg());
				$this->tracer->logError('[JSON ERROR]', __FILE__, __LINE__, PHP_EOL.$result_json);
				throw new Exception('json_decode error: '.json_last_error_msg());
			}

			if(isset($result['data']) === false || is_array($result['data']) === false){
				$this->tracer->logError('[DATA ERROR]', __FILE__, __LINE__, 'Incorrect show info');
				$this->tracer->logError('[DATA ERROR]', __FILE__, __LINE__, PHP_EOL.print_r($result, true));
				throw new RuntimeException('Incorrect show info: data element is not found');
			}

			$showInfoList = array_merge($showInfoList, $result['data']);
			$pos += self::showInfoStep;
		}while(count($result['data']) > 0);

		return $showInfoList;
	}
}

