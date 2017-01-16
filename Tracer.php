<?php
require_once(__DIR__.'/TracerBase.php');
require_once(__DIR__.'/config/stuff.php');

class Tracer extends TracerBase{
	private $hFile;

	public function __construct($traceName){
		assert(is_string($traceName));

		$traceFileName = __DIR__."/logs/$traceName.log";
		
		$traceExists = file_exists($traceFileName);

		$this->hFile = fopen($traceFileName, 'a');
		if($this->hFile === false){
			throw new Exception(
				"Unable to open $traceFileName file.".PHP_EOL.
				print_r(error_get_last(), true)
			);
		}
		
		if($traceExists === false){
			assert(chmod(__DIR__."/logs/$traceName.log", 0666));
		}

		parent::__construct($traceName);
	}

	public function __destruct(){
		parent::__destruct();
		assert(fclose($this->hFile));
	}

	protected function write($text){
		assert(is_string($text));
		
		$text .= PHP_EOL;
		
		assert(fwrite($this->hFile, $text));
	}

}
