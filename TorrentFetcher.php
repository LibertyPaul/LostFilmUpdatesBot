<?php
require_once(realpath(dirname(__FILE__)).'/config/stuff.php');

class TorrentFetcher{
	protected $torrentDir;
	protected $login;
	protected $password;
	protected $curl;
	protected $cookiesFilePath;
	protected $pdo;
	private $logFile;
	
	const TORRENT_DIR_NAME = 'torrentFiles/';
	const LOG_FNAME = 'TorrentFetcher.log.txt';
	
	public function __construct($login, $password){
		if(is_dir(self::TORRENT_DIR_NAME) === false)
			throw new Exception("torrentDir is not a valid path to directory");
		$this->torrentDir = self::TORRENT_DIR_NAME;
		
		if(is_string($login) === false)
			throw new Exception("invalid <login> type: ".var_dump($login, true));
		$this->login = $login;
		
		if(is_string($password) === false)
			throw new Exception("invalid <password> type: ".var_dump($password, true));
		$this->password = $password;
		
		$this->curl = curl_init();
		if($this->curl === false)
			throw new Exception(__METHOD__.' -> curl_init error');
		
		
		$tmp_dir = sys_get_temp_dir();
		if(is_writeable($tmp_dir) === false)
			throw new Exception(__METHOD__." -> $tmp_dir is not writeable");
			
		$this->cookiesFilePath = tempnam($tmp_dir, 'LFUB_cookies_');
		if($this->cookiesFilePath == false)
			throw new Exception(__METHOD__.' -> tempnam error');
		
		$this->pdo = createPDO();
		
		$path = realpath(dirname(__FILE__)).'/logs/'.self::LOG_FNAME;
		$this->logFile = createOrOpenLogFile($path);
		
		
		$this->loginRoutine();
	}
	
	public function __destruct(){
		curl_close($this->curl);
		
		$res = fclose($this->logFile);
		if($res === false)
			exit(__METHOD__." fclose error");
	}
	
	protected function log($text){
		$res = flock($this->logFile, LOCK_EX);
		if($res === false)
			throw new Exception(__METHOD__." flock LOCK_EX log file");
		
		$res = fseek($this->logFile, 0, SEEK_END);
		if($res === -1)
			throw new Exception(__METHOD__." fseek error");
		
		$res = fwrite($this->logFile, $text);
		if($res === false)
			throw new Exception(__METHOD__." fwrite error");
		
		$res = flock($this->logFile, LOCK_UN);
		if($res === false)
			throw new Exception(__METHOD__." flock LOCK_UN log file");
	}
			
	
	protected function getCookies(){
		$cookies = array();
		$fCookies = fopen($this->cookiesFilePath, 'r');
		if($fCookies === false)
			throw new Exception(__METHOD__." -> {$this->cookiesFilePath} fopen error");
	
		while($line = fgets($fCookies)){
			$commentPos = strpos($line, '#');
			if($commentPos !== false)
				$line = substr($line, 0, $commentPos);
			if(strlen(trim($line)) === 0)
				continue;
			$cookieData = explode("\t", $line);
			$cookies[] = trim($cookieData[5].'='.$cookieData[6]);
		}
		$res = fclose($fCookies);
		if($res === false)
			throw new Exception(__METHOD__." -> {$this->cookiesFilePath} fclose error");
		return $cookies;
	}
	
	protected function curlQuery($url, $POSTFields = null){
		$isPOST = $POSTFields !== null;
		
		$res = curl_setopt_array($this->curl,
			array(
				CURLOPT_URL 		=> $url,
				CURLOPT_RETURNTRANSFER 	=> true,
				CURLOPT_FOLLOWLOCATION 	=> false,
				CURLOPT_POST 		=> $isPOST,
				CURLOPT_POSTFIELDS 	=> $POSTFields,
				CURLOPT_USERAGENT 	=> 'Mozilla/5.0 AppleWebKit (KHTML, like Gecko) Chrome Safari',
				CURLOPT_VERBOSE 	=> false,
				CURLOPT_COOKIEJAR 	=> $this->cookiesFilePath,
				CURLOPT_COOKIEFILE 	=> $this->cookiesFilePath,
				CURLOPT_ENCODING	=> 'gzip'
			)
		);
		if($res === false)
			throw new Exception(__METHOD__.' -> curl_setopt_array error');

		$res = curl_exec($this->curl);
		if($res === false)
			throw new Exception(__METHOD__.' -> curl_exec error');
		
		return $res;
	}
		
		
		
	
	private function login(){
		$url = 'http://login1.bogi.ru/login.php?referer=https%3A%2F%2Fwww.lostfilm.tv%2F';
		$POSTFields = array(
			'login' 	=> $this->login,
			'password' 	=> $this->password,
			'module' 	=> 1,
			'target' 	=> 'http%3A%2F%2Flostfilm.tv%2F',
			'repage' 	=> 'user',
			'act' 		=> 'login'
		);
		$res = $this->curlQuery($url, $POSTFields);
		return $res;
	}

	private function submitLoginForm($url, $POSTFields){
		$res = $this->curlQuery($url, $POSTFields);
		return $res;
	}
	
	protected function loginRoutine(){//функция выполняет процедуры авторизации
		//логинимся
		$htmlCode = $this->login();//#1
		
		//парсим HTML ответ с формой и автосабмитом
		$urlRegexp = '/<form[\s\S]*?action="([^"]*)"/';

		$match = array();
		$res = preg_match($urlRegexp, $htmlCode, $match);
		if($res === false || $res === 0)
			throw new Exception(__METHOD__." url match failed");

		$url = $match[1];
		
		$fieldsRegexp = '/<input[\s\S]*?name="([^"]*)"[\s\S]*?value="([^"]*)"/';
		$matches = array();
		$res = preg_match_all($fieldsRegexp, $htmlCode, $matches);
		if($res === false || $res === 0)
			throw new Exception(__FUNCTION__." fields match failed");

		$POSTFields = array();
		for($i = 0; $i < $res; ++$i)
			$POSTFields[$matches[1][$i]] = $matches[2][$i];
			
		//эмулируем автосабмит посылая все поля формы вручную
		$htmlCode = $this->submitLoginForm($url, $POSTFields);//#2
	}
	
	public function getShowId($url_id){
		static $getShowId;
		if(isset($getShowId) === false){
			$getShowId = $this->pdo->prepare("
				SELECT `id`
				FROM `shows`
				WHERE `url_id` = :url_id
			");
		}
		$res = $getShowId->execute(
			array(
				':url_id' => $url_id
			)
		);
		if($res === false)
			throw new Exception(__METHOD__." PDO getShowId->execute error");
		
		$show = $getShowId->fetchObject();
		return $show->id;
	}
	
	public function getShowUrlId($show_id){
		static $getShowUrlId;
		if(isset($getShowUrlId) === false){
			$getShowUrlId = $this->pdo->prepare("
				SELECT `url_id`
				FROM `shows`
				WHERE `id` = :show_id
			");
		}
		$res = $getShowUrlId->execute(
			array(
				':show_id' => $show_id
			)
		);
		if($res === false)
			throw new Exception(__METHOD__." PDO getShowUrlId->execute error");
		
		$show = $getShowUrlId->fetchObject();
		return $show->url_id;
	}
	
	private function getDltCookie($show_id){//заходим на страницу сериала & получаем dlt_2 куку
		//TODO: похоже на то, что dlt_2 кука, полученная на странице сериала канает и для всех остальных сериалов
		$urlId = $this->getShowUrlId($show_id);
		$url = "http://www.lostfilm.tv/browse.php?cat=$urlId";
		$res = $this->curlQuery($url);
	
		return $res;
	}
	
	protected function convertSize($value, $unitName){
		switch($unitName){
		case 'ТБ':
			$value *= 1024;
		case 'ГБ':
			$value *= 1024;
		case 'МБ':
			$value *= 1024;
		case 'КБ':
			$value *= 1024;
		case 'Б':
			break;
		default:
			throw new Exception("Unknown size unit: $unitName");
		}
		
		return ceil($value);
	}
	
	public function downloadTorrentFile($url){//качает файл из $url в /tmp и возвращает путь к нему
		$torrentData = file_get_contents($url);
		if($torrentData === false)
			throw new Exception(__METHOD__." file_get_contents error");
		
		$tmpFile = tempnam('/tmp', 'torrent_');
		if($tmpFile === false)
			throw new Exception(__METHOD__." tempnam error");
		
		$res = file_put_contents($tmpFile, $torrentData);
		if($res !== strlen($torrentData))
			throw new Exception(__METHOD__." file_put_contents error");
			
		return $tmpFile;
	}
	
	public function getTorrentFilesPorperties($show_id, $seasonNumber, $seriesNumber){//заходит на retre.org и парсит все торрент файлы
		$this->getDltCookie($show_id);
		
		$urlId = $this->getShowUrlId($show_id);
		$query = http_build_query(
			array(
				'c' => $urlId,// === $urlId
				's' => $seasonNumber,// === "$seasonNumber.00"
				'e' => sprintf('%02d', $seriesNumber)//  === "0$seriesNumber"
			)
		);
		
		$url = "https://www.lostfilm.tv/nrdr2.php?$query";
		
		//запрос к nrdr2.php
		$htmlCode = $this->curlQuery($url);
		//эта шняга отдает HTML с редиректом
		//парсим & "редиректимся"
		
		$urlRegexp = '/<meta[\s\S]*?http-equiv="refresh"[\s\S]*?url=([^"]*)/';
		$match = array();
		$res = preg_match($urlRegexp, $htmlCode, $match);
		if($res === false || $res === 0)
			throw new Exception(__METHOD__." fields match failed");
		$url = $match[1];
		
		$htmlCode = $this->curlQuery($url);
		$htmlCode_utf8 = mb_convert_encoding($htmlCode, 'UTF-8', 'CP1251');
		//вот тут лежат заветные ссылки на торрент-файлы
		//парсим & качаем
		
		
		$torrentInfoRegexp = '/\t<a href="([^"]*)"[\s\S]*?Видео:  ?([^\.]+)\. Размер: ([^ ]+) (Б|КБ|МБ|ГБ|ТБ)/';
		/*
			1: Torrent file URL
			2: quality рипа (WEB-DL, WEB-DL 720p, ...)
			3: размер файла(не торрент)
			4: единица измерения (МБ, ГБ)
		*/
		
		$matches = array();
		$matchesCount = preg_match_all($torrentInfoRegexp, $htmlCode_utf8, $matches);
		if($matchesCount === false)
			throw new Exception(__METHOD__." preg_match_all error");
		if($matchesCount === 0)
			throw new Exception("$seriesNumber cерия $seasonNumber сезона не найдена...");
		
		
		
		$torrentsProperties = array();
		for($i = 0; $i < $matchesCount; ++$i){
			$torrentsProperties[] = array(
				'url' 		=> $matches[1][$i],
				'quality' 	=> $matches[2][$i],
				'fileSize' 	=> $this->convertSize($matches[3][$i], $matches[4][$i])
			);
		}
		return $torrentsProperties;
	}
	
	protected function isTorrentCached($show_id, $seasonNumber, $seriesNumber, $quality){//проверяет, есть ли торент файл в БД и на диске
		$isTorrentFileExists = $this->pdo->prepare("
			SELECT 
				`torrentFilename`
			FROM 	`torrentFiles`
			WHERE 	`show_id`			= :show_id
			AND	`seasonNumber`			= :seasonNumber
			AND	`seriesNumber`			= :seriesNumber
			AND	STRCMP(`quality`, :quality) 	= 0
		");
		
		$res = $isTorrentFileExists->execute(
			array(
				':show_id' 	=> $show_id,
				':seasonNumber' => $seasonNumber,
				':seriesNumber' => $seriesNumber,
				':quality' 	=> $quality
			)
		);
		if($res === false)
			throw new Exception(__METHOD__." isTorrentFileExists->execute error");
			
		$torrentFileInfoArray = $isTorrentFileExists->fetchAll();
				
		switch(count($torrentFileInfoArray)){
		case 1:
			$torrentPath = $this->torrentDir.$torrentFileInfoArray[0]['torrentFilename'];
			if(is_file($torrentPath)){
				return $torrentPath;
			}
		case 0:
			return false;
		default:
			throw new Exception("DB duplicate key detected: ".print_r($torrentFileInfoArray, true));
		}
	}
	
	protected function cacheTorrentFile($show_id, $seasonNumber, $seriesNumber, $quality, $fileSize, $url){
		$torrentFileName = uniqid("$show_id.$seasonNumber.$seriesNumber.").'.torrent';
		$torrentFilePath = $this->torrentDir.$torrentFileName;
		
		$tmpTorrentFilePath = $this->downloadTorrentFile($url);
		$res = rename($tmpTorrentFilePath, $torrentFilePath);
		if($res === false)
			throw new Exception(__METHOD__." rename error");
		
		$addTorrentFile = $this->pdo->prepare("
			INSERT IGNORE INTO `torrentFiles` (
				`torrentFilename`,
				`show_id`,
				`seasonNumber`,
				`seriesNumber`,
				`quality`,
				`filesize`
			)
			VALUES (
				:torrentFilename,
				:show_id,
				:seasonNumber,
				:seriesNumber,
				:quality,
				:filesize
			)
		");
		
		$res = $addTorrentFile->execute(
			array(
				':torrentFilename'	=> basename($torrentFilePath),
				':show_id' 		=> $show_id,
				':seasonNumber' 	=> $seasonNumber,
				':seriesNumber' 	=> $seriesNumber,
				':quality' 		=> $quality,
				':filesize' 		=> $sizeBytes
			)
		);
		if($res === false){
			$res = unlink($torrentFilePath);
			if($res === false)
				throw new Exception(__METHOD__." unlink error");
			throw new Exception(__METHOD__." addTorrentFile->execute error");
		}
		
		return $torrentFilePath;
	}
		
	
	public function fetchSeriesTorrents($show_id, $seasonNumber, $seriesNumber){//качает торрент-файлы для всех качеств если их нет в кеше.
		$torrentFilesProperties = $this->getTorrentFilesPorperties($show_id, $seasonNumber, $seriesNumber);
		$torrentsPaths = array();
		
		foreach($torrentFilesProperties as $torrentFileProperties){
			$torrentFilePath = $this->isTorrentCached(
				$show_id,
				$seasonNumber,
				$seriesNumber,
				$torrentFileProperties['quality']
			);
			if($torrentFilePath === false){		
				$torrentsPaths[] = $this->cacheTorrentFile(
					$show_id,
					$seasonNumber,
					$seriesNumber,
					$torrentFileProperties['quality'],
					$torrentFileProperties['fileSize'],
					$torrentFileProperties['url']
				);
			}
			else{
				$torrentsPaths[] = $torrentFilePath;
			}
		}
		
		return $torrentsPaths;
	}
}
























