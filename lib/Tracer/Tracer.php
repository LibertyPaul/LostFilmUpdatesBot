<?php
require_once(__DIR__.'/TracerBase.php');

class Tracer extends TracerBase{
	private $hFile = null;
	const logsDir = __DIR__.'/../../logs';
	const standaloneLogsDir = self::logsDir.'/standalone';

	public function __construct($traceName){
		if($traceName[0] === '-'){
			$traceName = substr($traceName, 1);
		}

		parent::__construct($traceName);
	}

	public function __destruct(){
		parent::__destruct();

		if($this->hFile !== null){
			assert(fclose($this->hFile));
		}
	}

	private static function createDirIfNotExists($dir){
		$prev_umask = umask(0);
		if(file_exists($dir)){
			if(is_dir($dir) === false){
				TracerBase::syslogCritical(
					'[SETUP]', __FILE__, __LINE__,
					"logs dir is not a directory ($dir)"
				);

				throw new \Exception("Unable to open $dir directory");
			}
		}
		else{
			assert(mkdir($dir, 0777, true));
		}
		umask($prev_umask);
	}
		

	private static function prepareTraceFile($path){
		$prev_umask = umask(0);
		self::createDirIfNotExists(dirname($path));
	
		if(file_exists($path) === false){
			assert(touch($path));
			assert(chmod($path, 0666));
		}
		
		$hFile = fopen($path, 'a');
		if($hFile === false){
			TracerBase::syslogCritical(
				'[SETUP]', __FILE__, __LINE__,
				"Unable to open file '$path'"
			);

			throw new \Exception("Unable to open $path file.".PHP_EOL.print_r(error_get_last(), true));
		}

		umask($prev_umask);
		return $hFile;
	}

	protected function storeStandalone($text){
		assert(is_string($text));

		$fName = $this->traceName.'.'.uniqid(rand(), true).'.txt';
		$fPath = self::standaloneLogsDir."/$fName";
		
		$hFile = self::prepareTraceFile($fPath);

		assert(fwrite($hFile, $text));
		assert(fclose($hFile));
		
		$this->write("<... Stored to standalone $fName ...>");
	}

	protected function write($text){
		assert(is_string($text));

		if($this->hFile === null){
			$this->hFile = self::prepareTraceFile(self::logsDir.'/'.$this->traceName.'.log');
		}
		
		$text .= PHP_EOL;
		
		assert(flock($this->hFile, LOCK_EX));
		assert(fwrite($this->hFile, $text));
		assert(flock($this->hFile, LOCK_UN));

	}

}
