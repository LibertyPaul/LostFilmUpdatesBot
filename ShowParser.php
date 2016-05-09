<?php

require_once(realpath(dirname(__FILE__))."/config/stuff.php");
require_once(realpath(dirname(__FILE__)).'/Parser.php');


class ShowParser extends Parser{
	protected $getShowIdQuery;
	protected $addShowQuery;

	public function __construct($pageEncoding = "utf-8"){
		parent::__construct($pageEncoding);
		$pdo = createPDO();
		
		$this->getShowIdQuery = $pdo->prepare('
			SELECT `id`
			FROM `shows`
			WHERE
				STRCMP(`title_ru`, :title_ru) = 0
			AND	STRCMP(`title_en`, :title_en) = 0
		');
		
		$this->addShowQuery = $pdo->prepare('
			INSERT INTO `shows` (title_ru, title_en)
			VALUES (:title_ru, :title_en)
		');
			
	}
	
	protected function isExists($title_ru, $title_en){
		$this->getShowIdQuery->execute(
			array(
				':title_ru' => $title_ru,
				':title_en' => $title_en
			)
		);
		
		$res = $this->getShowIdQuery->fetchAll();
		
		return count($res) > 0;
	}
			

	public function run(){
		$regexp = '/<a href="\/browse\.php\?cat=_?\d+" class="bb_a">([^<]*)<br><span>\(([^(]*)\)<\/span><\/a>/';
		$matches = array();
		$matchesCount = preg_match_all($regexp, $this->pageSrc, $matches);
		if($matchesCount === false){
			throw new Exception("preg_match_all error: ".preg_last_error());
		}
		
		for($i = 0; $i < $matchesCount; ++$i){
			$title_ru = $matches[1][$i];
			$title_en = $matches[2][$i];
			
			if($this->isExists($title_ru, $title_en) === false){
				$this->addShowQuery->execute(
					array(
						':title_ru' => $matches[1][$i],
						':title_en' => $matches[2][$i]
					)
				);
			}
		}
		
	}
}
	







