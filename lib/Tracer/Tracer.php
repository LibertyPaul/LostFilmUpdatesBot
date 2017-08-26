<?php
require_once(__DIR__.'/TracerBase.php');

class Tracer extends TracerBase{
	private $hFile = null;
	const logsDir = __DIR__.'/../../logs';
	const standaloneTracePath = self::logsDir.'/Standalone.log';

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
			$errorDescription = sprintf(
				'Unable to open file [%s]'.PHP_EOL.'%s',
				$path,
				print_r(error_get_last(), true)
			);

			TracerBase::syslogCritical('[SETUP]', __FILE__, __LINE__, $errorDescription);

			throw new \Exception($errorDescription);
		}

		umask($prev_umask);
		return $hFile;
	}

	protected function storeStandalone($text){
		assert(is_string($text));

		$id = uniqid($this->traceName.'_');
		
		$hFile = self::prepareTraceFile(self::standaloneTracePath);

		assert(flock($hFile, LOCK_EX));
		assert(fwrite($hFile, "id=[$id]"));
		assert(fwrite($hFile, PHP_EOL));
		assert(fwrite($hFile, $text));
		assert(fwrite($hFile, PHP_EOL));
		assert(fwrite($hFile, PHP_EOL));
		assert(flock($hFile, LOCK_UN));

		assert(fclose($hFile));
		
		$this->write("<... Stored to standalone with id=[$id] ...>");
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
