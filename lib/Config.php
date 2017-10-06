<?php

require_once(__DIR__.'/Tracer/Tracer.php');

abstract class ConfigFetchMode{
	const ALL_AT_ONCE = 0;
	const PER_REQUEST = 1;
}

class Config{
	private $cachedValues; // array('section' => array('item' => 'value'))
	private $getValueQuery;
	private $tracer;
	private $allCached = false;

	public function __construct(\PDO $pdo, $mode = ConfigFetchMode::ALL_AT_ONCE){
		$this->tracer = new \Tracer(__CLASS__);

		$this->getValueQuery = $pdo->prepare('
			SELECT	`value`
			FROM	`config`
			WHERE	`section`	= :section
			AND		`item`		= :item
		');

		$this->cachedValues = array();

		if($mode === ConfigFetchMode::ALL_AT_ONCE){
			$this->loadAllValues($pdo);
		}
	}

	private function cacheValue($section, $item, $value){
		if(array_key_exists($section, $this->cachedValues) === false){
			$this->cachedValues[$section] = array();
		}

		$this->cachedValues[$section][$item] = $value;
	}

	private function loadAllValues(\PDO $pdo){
		$getAllValuesQuery = $pdo->prepare('
			SELECT `section`, `item`, `value`
			FROM `config`
		');

		$this->tracer->logEvent(
			'[CONFIG]', __FILE__, __LINE__,
			'Loading all `config` values at once.'
		);

		$getAllValuesQuery->execute();

		while(($res = $getAllValuesQuery->fetch()) !== false){
			$this->cacheValue($res['section'], $res['item'], $res['value']);
			$this->tracer->logDebug(
				'[CONFIG]', __FILE__, __LINE__,
				sprintf('[%s][%s][%s]', $res['section'], $res['item'], $res['value'])
			);
		}
		
		$this->tracer->logEvent(
			'[CONFIG]', __FILE__, __LINE__,
			'All values were loaded.'
		);

		$this->allCached = true;
	}

	public function getValue($section, $item, $defaultValue = null){
		$this->tracer->logDebug(
			'[CONFIG GET]', __FILE__, __LINE__,
			"Config::getValue(section=[$section], item=[$item])"
		);

		if(array_key_exists($section, $this->cachedValues)){
			if(array_key_exists($item, $this->cachedValues[$section])){
				$value = $this->cachedValues[$section][$item];

				if($value !== null){
					$this->tracer->logDebug(
						'[CONFIG GET]', __FILE__, __LINE__,
						"Value=[$value] was found in cache"
					);

					return $value;
				}
				else{
					$this->tracer->logNotice(
						'[CONFIG GET]', __FILE__, __LINE__,
						"Absence of [$section][$item] is already cached"
					);

					return $defaultValue;
				}	
			}
		}

		if($this->allCached){
			return $defaultValue;
		}

		$this->tracer->logDebug(
			'[CONFIG GET]', __FILE__, __LINE__,
			'Cache miss, selecting from DB...'
		);

		$this->getValueQuery->execute(
			array(
				':section'	=> $section,
				':item'		=> $item
			)
		);

		$result = $this->getValueQuery->fetch();
		if($result === false){
			$this->tracer->logNotice(
				'[CONFIG GET]', __FILE__, __LINE__,
				"Requested value [$section][$item] does not exist"
			);

			$this->cacheValue($section, $item, null);

			return $defaultValue;
		}

		$value = $result['value'];

		$this->tracer->logDebug(
			'[CONFIG GET]', __FILE__, __LINE__,
			"Value was selected [$value]"
		);

		$this->cacheValue($section, $item, $value);

		return $value;
	}
}
