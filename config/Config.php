<?php

class Config{
	private $cachedValues; // array('section' => array('item' => 'value'))
	private $getValueQuery;

	public function __construct(PDO $pdo){
		assert($pdo !== null);

		$this->getValueQuery = $pdo->prepare('
			SELECT	`value`
			FROM	`config`
			WHERE	`section`	= :section
			AND		`item`		= :item
		');

		$this->cachedValues = array();
	}

	private function cacheValue($section, $item, $value){
		if(array_key_exists($section, $this->cachedValues) === false){
			$this->cachedValues[$section] = array();
		}

		$this->cachedValues[$section][$item] = $value;
	}

	public function getValue($section, $item){
		if(array_key_exists($section, $this->cachedValues)){
			if(array_key_exists($item, $this->cachedValues[$section])){
				return $this->cachedValues[$section][$item];
			}
		}

		$this->getValueQuery->execute(
			array(
				':section'	=> $section,
				':item'		=> $item
			)
		);

		$result = $this->getValueQuery->fetch();
		if($result === false){
			return null;
		}

		$value = $result['value'];

		$this->cacheValue($section, $item, $value);

		return $value;
	}
}
