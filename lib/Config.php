<?php

require_once(__DIR__.'/Tracer/TracerFactory.php');

abstract class ConfigFetchMode{
	const ALL_AT_ONCE = 0;
	const PER_REQUEST = 1;
}

class Config{
	private $cachedValues; // array('section' => array('item' => 'value'))
	private $getValueQuery;
	private $tracer;
	private $allCached = false;

	private function __construct(\PDO $pdo, int $mode){
		$this->tracer = \TracerFactory::getTracer(__CLASS__, $pdo);

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

	public static function getConfig(\PDO $pdo, int $mode = ConfigFetchMode::ALL_AT_ONCE){
		static $config;

		if(isset($config) === false){
			$config = new self($pdo, $mode);
		}

		return $config;
	}

	private function cacheValue(string $section, string $item, string $value){
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

		$getAllValuesQuery->execute();

		while(($res = $getAllValuesQuery->fetch()) !== false){
			$this->cacheValue($res['section'], $res['item'], $res['value']);
			$this->tracer->logDebug(
				'[CONFIG]', __FILE__, __LINE__,
				sprintf('[%s][%s][%s]', $res['section'], $res['item'], $res['value'])
			);
		}
		
		$this->allCached = true;
	}

	public function getValue(string $section, string $item, string $defaultValue = null): ?string {
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
					$this->tracer->logDebug(
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

		$this->getValueQuery->execute(
			array(
				':section'	=> $section,
				':item'		=> $item
			)
		);

		$result = $this->getValueQuery->fetch();
		if($result === false){
			$this->tracer->logDebug(
				'[CONFIG GET]', __FILE__, __LINE__,
				"Requested value [$section][$item] does not exist"
			);

			$this->cacheValue($section, $item, null);

			return $defaultValue;
		}

		$value = $result['value'];

		$this->cacheValue($section, $item, $value);

		return $value;
	}
}
