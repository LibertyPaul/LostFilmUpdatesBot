<?php

require_once(realpath(dirname(__FILE__))."/config/stuff.php");
require_once(realpath(dirname(__FILE__)).'/Parser.php');


class ShowParser extends Parser{
	public function __construct($pageEncoding = "utf-8"){
		parent::__construct($pageEncoding);
	}

	public function run(){
		$pdo = createPDO();
		$preparedQuery = $pdo->prepare("
			INSERT IGNORE INTO `shows` (title_ru, title_en, url_id)
			VALUES (:title_ru, :title_en, :url_id)
		");
		
		
		$regexp = '/<a href="\/browse\.php\?cat=_?(\d+)" class="bb_a">([^<]*)<br><span>\(([^(]*)\)<\/span><\/a>/';
		$matches = array();
		$matchesCount = preg_match_all($regexp, $this->pageSrc, $matches);
		if($matchesCount === false)
			throw new Exception("preg_match_all error: ".preg_last_error());
		
		
		for($i = 0; $i < $matchesCount; ++$i){
			$res = $preparedQuery->execute(
				array(
					':url_id' => $matches[1][$i],
					':title_ru' => $matches[2][$i],
					':title_en' => $matches[3][$i]
				)
			);
			if($res === false)
				throw new Exception("PDO execute error");
		}
		
	}
}
	







