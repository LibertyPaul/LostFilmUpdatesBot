<?php
require_once(realpath(dirname(__FILE__)).'/config/stuff.php');

class TorrentFetcher{
	protected $login;
	protected $password;
	protected $curl;
	protected $cookiesFilePath;
	private $logFile;
	
	const LOG_FNAME = 'TorrentFetcher.log.txt';
	const TORRENT_DIR_PATH = 'torrentFiles/';
	
	public function __construct($login, $password){
		$this->login = $login;
		$this->password = $password;
		
		$this->curl = curl_init();
		if($this->curl === false)
			throw new Exception(__METHOD__.' -> curl_init error');
		
		$this->cookiesFilePath = tempnam('/tmp', 'LFUB_cookies_');
		if($this->cookiesFilePath == false)
			throw new Exception(__METHOD__.' -> tempnam error');
			
		
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
				CURLOPT_FOLLOWLOCATION 	=> true,
				CURLOPT_POST 		=> $isPOST,
				CURLOPT_POSTFIELDS 	=> $POSTFields,
				CURLOPT_USERAGENT 	=> 'Mozilla/5.0 AppleWebKit (KHTML, like Gecko) Chrome Safari',
				CURLOPT_VERBOSE 	=> false,
				CURLOPT_COOKIEJAR 	=> $this->cookiesFilePath,
				CURLOPT_COOKIEFILE 	=> $this->cookiesFilePath
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
			throw new Exception(__METHOD__." fields match failed");

		$POSTFields = array();
		for($i = 0; $i < $res; ++$i)
			$POSTFields[$matches[1][$i]] = $matches[2][$i];
			
			
		//эмулируем автосабмит посылая все поля формы вручную
		$htmlCode = $this->submitLoginForm($url, $POSTFields);//#2
	}
	
	protected function getShowUrlId($show_id){
		$pdo = createPDO();
		static $getShowUrlId = $pdo->prepare("
			SELECT `url_id`
			FROM `shows`
			WHERE `id` = :show_id
		");
		
		$res = $getShowUrlId->execute(
			array(
				':show_id' => $show_id
			)
		);
		if($res === false)
			throw new Exception(__METHOD__." PDO getShowUrlId->execute error");
		
		$show = $res->fetchObject();
		return $show->id;
	}
	
	private function getDltCookie($show_id){//заходим на страницу сериала & получаем dlt_2 куку
		//TODO: похоже на то, что dlt_2 кука, полученная на странице сериала канает и для всех остальных сериалов
		$urlId = $this->getShowUrlId($show_id);
		$url = "http://www.lostfilm.tv/browse.php?cat=$urlId";
		$res = $this->curlQuery($url);
	
		return $res;
	}
	
	static public function getTorrentDir(){
		return realpath(dirname(__FILE__)).'/'.TORRENT_DIR_PATH;
	}
	
	public function downloadSeriesTorrents($show_id, $seasonNumber, $seriesNumber){//качает торрент-файлы для всех качеств(если для этой серии уже есть торрент-файлы, то добавляет(или нет))
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
			exit("fields match failed");
		$url = $match[1];
		
		
		$htmlCode = $this->curlQuery($url);
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
		$res = preg_match_all($torrentInfoRegexp, $htmlCode, $matches);
		if($res === false || $res === 0)
			throw new Exception(__METHOD__." preg_match_all error");
		
		
		$pdo = createPDO();
		$isTorrentFileExists = $pdo->prepare("
			SELECT COUNT(*) AS `count`
			FROM `torrentFiles`
			WHERE 	`show_id`			= :show_id
			AND	`seasonNumber`			= :seasonNumber
			AND	`seriesNumber`			= :seriesNumber
			AND	STRCMP(`quality`, :quality) 	= 0
		");
		
		
		$addTorrentFile = $pdo->prepare("
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
		
		for($i = 0; $i < $res; ++$i){
			$url = $matches[1][$i];
			$quality = $matches[2][$i];
			$size = $matches[3][$i];
			$unit = $matches[4][$i];
			
			$sizeBytes = $size;
			switch($unit){
			case 'ТБ':
				$sizeKB *= 1024;
			case 'ГБ':
				$sizeKB *= 1024;
			case 'МБ':
				$sizeKB *= 1024;
			case 'КБ':
				$sizeKB *= 1024;
			case 'Б':
				break;
			default:
				throw new Exception("Unknown size unit: $unit");
			}
			
			$res = $isTorrentFileExists->execute(
				array(
					':show_id' 	=> $show_id,
					':seasonNumber' => $seasonNumber,
					':seriesNumber' => $seriesNumber
					':quality' 	=> $quality
				)
			);
			if($res === false)
				throw new Exception(__METHOD__." isTorrentFileExists->execute error");
			
			$res_obj = $isTorrentFileExists->fetchObject();
			$count = $res_obj->count;//количество торрени файлов с заданным качеством
			if($count === 0){			
				$torrentFileNamePrefix = "$show_id.$seasonNumber.$seriesNumber.";
				$torrentFilePath = tempnam(self::getTorrentDir(), $torrentFileNamePrefix).".torrent";
				/*
				if(is_file($torrentFilePath))
					throw new Exception("Torrent file is already exists");
				*/
				$torrentData = $this->curlQuery($url);
				$res = file_put_contents($torrentFilePath, $torrentData, LOCK_EX);
				if($res === false)
					throw new Exception(__METHOD__." file_put_contents error");
				
				
				$res = $addTorrentFile->execute(
					array(
						':torrentFilename'	=> basename($torrentFilePath)
						':show_id' 		=> $show_id,
						':seasonNumber' 	=> $seasonNumber,
						':seriesNumber' 	=> $seriesNumber
						':quality' 		=> $quality
						':filesize' 		=> $sizeBytes
					)
				);
				if($res === false)
					throw new Exception(__METHOD__." addTorrentFile->execute error");
			}
		}
	}
}
























