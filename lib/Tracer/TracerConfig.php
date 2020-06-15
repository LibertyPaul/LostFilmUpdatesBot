<?php

require_once(__DIR__.'/TracerLevel.php');

class TracerConfig{
	private $standaloneIfLargerThan = 5000;
	private $loggingLevel = TracerLevel::Debug;
	private $CLIStdOutTrace = false;
	private $LinuxGroup = Null;

	public function __construct(string $configIniPath, string $traceName = null){
		if(file_exists($configIniPath)){
			$configIni = $this->parseIniFile($configIniPath);

			if(
				isset($configIni['Default']) &&
				is_array($configIni['Default'])
			){
				$this->fetchSectionValues($configIni['Default']);
			}

			if(
				$traceName !== null &&
				isset($configIni["Custom.$traceName"]) &&
				is_array($configIni["Custom.$traceName"])
			){
				$this->fetchSectionValues($configIni["Custom.$traceName"]);
			}
		}
	}

	private function parseIniFile(string $configIniPath): array{
		$data = parse_ini_file($configIniPath, true, INI_SCANNER_RAW);
		if($data === false){	
			throw new \RuntimeException("Unable to parse ini file [$configIniPath]");
		}

		return $data;
	}		

	private function fetchSectionValues(array $configIniSection){
		if(isset($configIniSection['StandaloneIfLargerThan'])){
			$standaloneIfLargerThan = $configIniSection['StandaloneIfLargerThan'];
			if(is_numeric($standaloneIfLargerThan) === false){
				throw new \RuntimeException(
					"Incorrect [StandaloneIfLargerThan] type: ".
					gettype($standaloneIfLargerThan)
				);
			}

			$this->standaloneIfLargerThan = intval($standaloneIfLargerThan);
		}

		if(isset($configIniSection['LoggingLevel'])){
			$loggingLevel = $configIniSection['LoggingLevel'];
			if(is_string($loggingLevel) === false){
				throw new \RuntimeException(
					"Incorrect [LoggingLevel] type: ".
					gettype($loggingLevel)
				);
			}

			$this->loggingLevel = \TracerLevel::getLevelByName($loggingLevel);
		}

		if(isset($configIniSection['CLIStdOutTrace'])){
			switch($configIniSection['CLIStdOutTrace']){
				case 'true':
					$this->CLIStdOutTrace = true;
					break;

				case 'false':
					$this->CLIStdOutTrace = false;
					break;

				default:
					throw new \RuntimeException(
						"Incorrect [CLIStdOutTrace] value: [$CLIStdOutTrace]"
					);
			}
		}

		if(isset($configIniSection['LinuxGroup'])){
			$this->LinuxGroup = $configIniSection['LinuxGroup'];
		}

	}

	public function getStandaloneIfLargerThan(): int{
		return $this->standaloneIfLargerThan;
	}

	public function getLoggingLevel(): int{
		return $this->loggingLevel;
	}

	public function getCLIStdOutTrace(): bool{
		return $this->CLIStdOutTrace;
	}

	public function getLinuxGroup(): ?string{
		return $this->LinuxGroup;
	}

	public function __toString(){
		$standaloneIfLargerThanStr = $this->standaloneIfLargerThan;
		$loggingLevelStr = \TracerLevel::getNameByLevel($this->loggingLevel);
		$CLIStdOutTraceStr = $this->CLIStdOutTrace ? 'Y' : 'N';
		$LinuxGroupStr = $this->LinuxGroup ?? '<Null>';

		$res  = '|----------------[Tracer Config]----------------|'			.PHP_EOL;
		$res .= "Standalone If Larger Than:  [$standaloneIfLargerThanStr]"	.PHP_EOL;
		$res .= "Logging Level:              [$loggingLevelStr]"			.PHP_EOL;
		$res .= "CLI StdOut Trace:           [$CLIStdOutTraceStr]"			.PHP_EOL;
		$res .= "Linux Group:                [$LinuxGroupStr]"				.PHP_EOL;
		$res .= '|-----------------------------------------------|';

		return $res;
	}
}
				
