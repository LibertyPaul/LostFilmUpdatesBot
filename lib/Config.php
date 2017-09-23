<?php

require_once(__DIR__.'/Tracer/Tracer.php');

class Config{
	private $cachedValues; // array('section' => array('item' => 'value'))
	private $getValueQuery;
	private $tracer;

	public function __construct(PDO $pdo){
		assert($pdo !== null);

		$this->tracer = new \Tracer(__CLASS__);

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

	public function getValue($section, $item, $defaultValue = null){
		$this->tracer->logDebug('[CONFIG GET]', __FILE__, __LINE__, "section=[$section], item=[$item]");
		if(array_key_exists($section, $this->cachedValues)){
			if(array_key_exists($item, $this->cachedValues[$section])){
				$value = $this->cachedValues[$section][$item];
				$this->tracer->logDebug('[CONFIG GET]', __FILE__, __LINE__, "value=[$value] was found in cache");
				return $value;
			}
		}

		$this->tracer->logDebug('[CONFIG GET]', __FILE__, __LINE__, 'Cache miss, selecting from DB');

		$this->getValueQuery->execute(
			array(
				':section'	=> $section,
				':item'		=> $item
			)
		);

		$result = $this->getValueQuery->fetch();
		if($result === false){
			$this->tracer->logNotice('[CONFIG GET]', __FILE__, __LINE__, "Requested value [$section][$item] does not exist");
			return $defaultValue;
		}

		$value = $result['value'];

		$this->tracer->logDebug('[CONFIG GET]', __FILE__, __LINE__, "Value was selected [$value]");

		$this->cacheValue($section, $item, $value);

		return $value;
	}
}
