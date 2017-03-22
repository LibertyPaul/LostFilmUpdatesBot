<?php
require_once(__DIR__.'/ParserPDO.php');
require_once(__DIR__.'/Exceptions/StdoutTextException.php');
require_once(__DIR__.'/ShowAboutParser.php');
require_once(__DIR__.'/Tracer.php');


class ShowParser{
	private $requester;

	private $getShowIdQuery;
	private $addShowQuery;
	private $updateOnAirQuery;
	private $updateShowAliasQuery;

	private $showAboutParser;
	private $tracer;
	
	const showInfoTemplate	= 'https://www.lostfilm.tv/ajaxik.php?act=serial&type=search&o=#FROM&s=3&t=0';
	const showInfoStep		= 10;

	public function __construct(HTTPRequesterInterface $requester, $pageEncoding = 'utf-8'){
		assert($requester !== null);
		$this->requester = $requester;

		$this->tracer = new Tracer(__CLASS__);

		$pdo = ParserPDO::getInstance();
		$this->showAboutParser = new ShowAboutParser($requester);
		
		$this->getShowIdQuery = $pdo->prepare('
			SELECT `id`
			FROM `shows`
			WHERE	STRCMP(`title_ru`, :title_ru) = 0
			AND		STRCMP(`title_en`, :title_en) = 0
		');
		
		$this->addShowQuery = $pdo->prepare('
			INSERT INTO `shows` (alias, title_ru, title_en, onAir)
			VALUES (:alias, :title_ru, :title_en, :onAir)
		');

		$this->updateOnAirQuery = $pdo->prepare('
			UPDATE `shows` SET `onAir` = :onAir WHERE `id` = :id
		');

		$this->updateShowAliasQuery = $pdo->prepare('
			UPDATE `shows` SET `alias` = :alias WHERE id = :id
		');
		
	}
	
	protected function getShowId($title_ru, $title_en){
		$this->getShowIdQuery->execute(
			array(
				':title_ru' => $title_ru,
				':title_en' => $title_en
			)
		);
		
		$res = $this->getShowIdQuery->fetch(PDO::FETCH_ASSOC);
		if($res === false){
			return null;
		}
		
		return $res['id'];
	}

	
	private function getShowsInfoURL($from){
		assert(is_int($from));
		return str_replace('#FROM', $from, self::showInfoTemplate);
	}

	private function getShowInfoList(){
		$showInfoList = array();
		$pos = 0;

		do{
			$url = $this->getShowsInfoURL($pos);
			try{
				$result = $this->requester->sendGETRequest($url);
			}
			catch(HTTPException $ex){
				$this->tracer->logException($ex);
				throw $ex;
			}

			$result_json = $result['value'];

			$result = json_decode($result_json, true);
			if($result === false){
				$this->tracer->log('[JSON ERROR]', __FILE__, __LINE__, 'json_decode error: '.json_last_error_msg());
				$this->tracer->log('[JSON ERROR]', __FILE__, __LINE__, PHP_EOL.$result_json);
				throw new Exception('json_decode error: '.json_last_error_msg());
			}

			if(isset($result['data']) === false || is_array($result['data']) === false){
				$this->tracer->log('[DATA ERROR]', __FILE__, __LINE__, 'Incorrect show info');
				$this->tracer->log('[DATA ERROR]', __FILE__, __LINE__, PHP_EOL.print_r($result, true));
				throw new RuntimeException('Incorrect show info: data element is not found');
			}

			$showInfoList = array_merge($showInfoList, $result['data']);
			$pos += self::showInfoStep;
		}while(count($result['data']) > 0);

		return $showInfoList;
	}
	
	
	public function run(){
		$showInfoList = $this->getShowInfoList();
		foreach($showInfoList as $showInfo){
			try{
				$showId = $this->getShowId($showInfo['title'], $showInfo['title_orig']);

				$this->updateShowAliasQuery->execute(
					array(
						':id'		=> $showId,
						':alias'	=> $showInfo['alias']
					)
				);

				$onAir = intval($showInfo['status']) !== 5;
				$onAirFlag = $onAir ? 'Y' : 'N';
				if($showId === null){
					$this->tracer->log('[NEW SHOW]', __FILE__, __LINE__, "$showInfo[title] ($showInfo[title_orig])");
					
					$this->addShowQuery->execute(
						array(
							':alias'	=> $showInfo['alias'],
							':title_ru' => $showInfo['title'],
							':title_en' => $showInfo['title_orig'],
							':onAir'	=> $onAirFlag
						)
					);
				}
				else{
					$this->updateOnAirQuery->execute(
						array(
							':onAir'	=> $onAirFlag,
							':id'		=> $showId
						)
					);
				}
			}
			catch(PDOException $ex){
				$this->tracer->logException('[DB ERROR]', $ex);
				$this->tracer->log('[DB ERROR]', __FILE__, __LINE__, PHP_EOL.print_r($showInfo, true));
			}
			catch(Exception $ex){
				$this->tracer->logException('[ERROR]', $ex);
				$this->tracer->log('[DB ERROR]', __FILE__, __LINE__, PHP_EOL.print_r($showInfo, true));
			}
		}
	}
}
	







