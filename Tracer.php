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
		
		$traceExists = file_exists($traceFileName);

		$this->hFile = fopen($traceFileName, 'a');
		if($this->hFile === false){
			syslog(LOG_CRIT, "[Tracer] Unable to open file '$traceFileName'");
			throw new Exception("Unable to open $traceFileName file.".PHP_EOL.print_r(error_get_last(), true));
		}
		
		if($traceExists === false){
			assert(chmod($traceFileName, 0666));
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
