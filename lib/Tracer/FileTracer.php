<?php
require_once(__DIR__.'/TracerCompiled.php');

class FileTracer extends TracerCompiled{
	private $traceName;
	private $hFile = null;
	const logsDir = __DIR__.'/../../logs';
	const standaloneTracePath = self::logsDir.'/Standalone.log';

	public function __construct(string $traceName, TracerBase $nextTracer = null){
		parent::__construct(
			new TracerConfig(__DIR__.'/FileTracerConfig.ini', $traceName),
			$nextTracer
		);

		if($traceName[0] === '-'){
			$traceName = substr($traceName, 1);
		}

		$traceName = str_replace('\\', '.', $traceName);

		$this->traceName = $traceName;
	}

	public function __destruct(){
		parent::__destruct();

		if($this->hFile !== null){
			assert(fclose($this->hFile));
		}
	}

	private function createDirIfNotExists(string $dir){
		$prev_umask = umask(0);
		if(file_exists($dir)){
			if(is_dir($dir) === false){
				parent::syslogCritical(
					'[SETUP]', __FILE__, __LINE__,
					"logs dir is not a directory ($dir)"
				);

				throw new \Exception("Unable to open $dir directory");
			}
		}
		else{
			$group = $this->config->getLinuxGroup();
			if($group !== null){
				assert(mkdir($dir, 0770, true));
				assert(chgrp($dir, $group));
			}
			else{
				assert(mkdir($dir, 0777, true));
			}
		}
		umask($prev_umask);
	}
		

	private function prepareTraceFile(string $path){
		$prev_umask = umask(0);
		$this->createDirIfNotExists(dirname($path));
	
		if(file_exists($path) === false){
			assert(touch($path));

			$group = $this->config->getLinuxGroup();
			if($group !== null){
				assert(chgrp($path, $group));
			}
			else{
				assert(mkdir($path, 0666, true));
			}
		}
		
		$hFile = fopen($path, 'a');
		if($hFile === false){
			$errorDescription = sprintf(
				'Unable to open file [%s]'.PHP_EOL.'%s',
				$path,
				print_r(error_get_last(), true)
			);

			parent::syslogCritical('[SETUP]', __FILE__, __LINE__, $errorDescription);

			throw new \Exception($errorDescription);
		}

		umask($prev_umask);
		return $hFile;
	}

	protected function storeStandalone(string $text){
		$id = uniqid($this->traceName.'_');
		
		$hFile = $this->prepareTraceFile(self::standaloneTracePath);

		assert(flock($hFile, LOCK_EX));
		assert(fwrite($hFile, "id=[$id]"));
		assert(fwrite($hFile, PHP_EOL));
		assert(fwrite($hFile, $text));
		assert(fwrite($hFile, PHP_EOL));
		assert(fwrite($hFile, PHP_EOL));
		assert(flock($hFile, LOCK_UN));

		assert(fclose($hFile));
		
		$this->write("<... Stored to standalone with id=[$id] ...>");

		if($this->config->getCLIStdOutTrace() && strpos(php_sapi_name(), 'cli') !== false){
			echo $text;
		}

	}

	protected function write(string $text){
		if($this->hFile === null){
			$this->hFile = $this->prepareTraceFile(self::logsDir.'/'.$this->traceName.'.log');
		}
		
		$text .= PHP_EOL;

		assert(flock($this->hFile, LOCK_EX));
		assert(fwrite($this->hFile, $text));
		assert(flock($this->hFile, LOCK_UN));

		if($this->config->getCLIStdOutTrace() && strpos(php_sapi_name(), 'cli') !== false){
			echo $text;
		}

	}

}
