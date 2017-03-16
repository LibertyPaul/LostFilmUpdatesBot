<?php
require_once(__DIR__.'/TracerBase.php');
require_once(__DIR__.'/config/stuff.php');

class Tracer extends TracerBase{
	private $hEventLog = null;
	const eventLogExtension = 'event.log';

	private $hErrorLog = null;
	const errorLogExtension = 'error.log';

	public function __construct($traceName){
		parent::__construct($traceName);
	}

	public function __destruct(){
		parent::__destruct();
		if($this->hEventLog !== null){
			assert(fclose($this->hEventLog));
		}

		if($this->hErrorLog !== null){
			assert(fclose($this->hErrorLog));
		}
	}

	private static function openFile($path){
		$traceDir = dirname($path);
		
		if(file_exists($traceDir)){
			if(is_dir($traceDir) === false){
				syslog(LOG_CRIT, "[TRACER] logs dir is not a directory ($traceDir)");
				throw new Exception("Unable to open $traceDir directory");
			}
			$traceExists = file_exists($path);
		}
		else{
			assert(mkdir($traceDir, 0777, true));
			$traceExists = false;
		}

		if($traceExists === false){
			assert(touch($path));
			assert(chmod($path, 0666));
		}
		
		
		$hFile = fopen($path, 'a');
		if($hFile === false){
			syslog(LOG_CRIT, "[Tracer] Unable to open file '$path'");
			throw new Exception("Unable to open $path file.".PHP_EOL.print_r(error_get_last(), true));
		}

		return $hFile;
	}

	private function getPath($extension){
		return __DIR__.'/logs/'.$this->traceName.".$extension";
	}

	private function write($text, $hFile){
		assert(is_string($text));
		assert($hFile !== null);

		$text .= PHP_EOL;
		
		assert(fwrite($hFile, $text));
	}

	protected function writeEvent($text){
		if($this->hEventLog === null){
			$this->hEventLog = self::openFile($this->getPath(self::eventLogExtension));
		}
		
		$this->write($text, $this->hEventLog);
	}

	protected function writeError($text){
		if($this->hErrorLog === null){
			$this->hErrorLog = self::openFile($this->getPath(self::errorLogExtension));
		}

		$this->write($text, $this->hErrorLog);
	}
}
