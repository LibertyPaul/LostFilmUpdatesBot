<?php
require_once(__DIR__.'/TracerBase.php');
require_once(__DIR__.'/config/stuff.php');

class Tracer extends TracerBase{
	private $hFile = null;
	private $fName;

	public function __construct($traceName){
		parent::__construct($traceName);
	}

	public function __destruct(){
		parent::__destruct();
		if($this->hFile !== null){
			assert(fclose($this->hFile));
		}
	}

	private function openFile(){
		$traceFileName = __DIR__.'/logs/'.$this->traceName.'.log';
		$traceDir = dirname($traceFileName);
		
		if(file_exists($traceDir)){
			if(is_dir($traceDir) === false){
				syslog(LOG_CRIT, "[TRACER] logs dir is not a directory ($traceDir)");
				throw new Exception("Unable to open $traceDir directory");
			}
			$traceExists = file_exists($traceFileName);
		}
		else{
			assert(mkdir($traceDir, 0777, true));
			$traceExists = false;
		}

		if($traceExists === false){
			assert(touch($traceFileName));
			assert(chmod($traceFileName, 0666));
		}
		
		
		$this->hFile = fopen($traceFileName, 'a');
		if($this->hFile === false){
			syslog(LOG_CRIT, "[Tracer] Unable to open file '$traceFileName'");
			throw new Exception("Unable to open $traceFileName file.".PHP_EOL.print_r(error_get_last(), true));
		}
		
	}

	protected function write($text){
		assert(is_string($text));

		if($this->hFile === null){
			$this->openFile();
		}
		
		$text .= PHP_EOL;
		
		assert(fwrite($this->hFile, $text));
	}

}
