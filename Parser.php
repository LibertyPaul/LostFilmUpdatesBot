<?php
abstract class Parser{
	protected $pageSrc;
	protected $srcEncoding;

	public function __construct($srcEncoding){
		if(isset($srcEncoding)){
			$this->srcEncoding = $srcEncoding;
		}
		else{
			$this->srcEncoding = null;
		}
	}
	
	public function loadSrc($path){
		$this->pageSrc = file_get_contents($path);
		if($this->pageSrc === false){
			throw new Exception('file_get_contents() error');
		}

		if($this->srcEncoding !== null){
			$this->pageSrc = mb_convert_encoding($this->pageSrc, 'UTF-8', $this->srcEncoding);
		}
	}
	
	abstract public function run();
}
