<?php

require_once(__DIR__.'/TracerLevel.php');

class TracerConfig{
	private $standaloneIfLargerThan = 5000;
	private $loggingLevel = TracerLevel::Debug;
	private $logStartedFinished = false;

	public function __construct($configIniPath, $traceName){
		if(file_exists($configIniPath)){
			$configIni = $this->parseIniFile($configIniPath);
			$this->fetchSectionValues($configIni, 'Common');
			$this->fetchSectionValues($configIni, $traceName);
		}
	}

	private function parseIniFile($configIniPath){
		$data = parse_ini_string($configIniPath, true, INI_SCANNER_RAW);
		if($data === false){	
			throw new \RuntimeException("Unable to parse ini file [$configIniPath]");
		}

		return $data;
	}		

	private function fetchSectionValues(array $configIni, $section = 'Common'){
		if(isset($configIni[$section]) === false){
			return;
		}

		if(is_array($configIni[$section]) === false){
			throw new \RuntimeException("[$section] is not a section");
		}

		if(isset($configIni[$section]['StandaloneIfLargerThan'])){
			$standaloneIfLargerThan = $configIni[$section]['StandaloneIfLargerThan'];
			if(is_numeric($standaloneIfLargerThan) === false){
				throw new \RuntimeException(
					"Incorrect [$section][StandaloneIfLargerThan] type: ".
					gettype($standaloneIfLargerThan)
				);
			}

			$this->standaloneIfLargerThan = intval($standaloneIfLargerThan);
		}

		if(isset($configIni[$section]['LoggingLevel'])){
			$loggingLevel = $configIni[$section]['LoggingLevel'];
			if(is_string($loggingLevel) === false){
				throw new \RuntimeException(
					"Incorrect [$section][LoggingLevel] type: ".
					gettype($loggingLevel)
				);
			}

			$this->loggingLevel = \TraceLevel::getLevelByName($loggingLevel);
		}

		if(isset($configIni[$section]['LogStartedFinished'])){
			$logStartedFinished = $configIni[$section]['LogStartedFinished'];
			switch($logStartedFinished){
				case 'true':
					$this->logStartedFinished = true;
					break;
				
				case 'false':
					$this->logStartedFinished = false;
					break;

				default:
					throw new \RuntimeException(
						"Incorrect [$section][LogStartedFinished] value: [$logStartedFinished]"
					);
			}
		}
	}

	public function getStandaloneIfLargerThan(){
		return $this->standaloneIfLargerThan;
	}

	public function getLoggingLevel(){
		return $this->loggingLevel;
	}

	public function getLogStartedFinished(){
		return $this->logStartedFinished;
	}

	public function __toString(){
		$standaloneIfLargerThanStr = $this->standaloneIfLargerThan;
		$loggingLevelStr = \TracerLevel::getNameByLevel($this->loggingLevel);
		$logStartedFinishedStr = $this->logStartedFinished ? 'Y' : 'N';

		$res  = '|----------[Tracer Config]----------|'						.PHP_EOL;
		$res .= "Standalone If Larger Than:  [$standaloneIfLargerThanStr]"	.PHP_EOL;
		$res .= "Logging Level:              [$loggingLevelStr]"			.PHP_EOL;
		$res .= "LogStarted Finished:        [$logStartedFinishedStr]"		.PHP_EOL;
		$res .= '|-----------------------------------|';

		return $res;
	}
}
				
