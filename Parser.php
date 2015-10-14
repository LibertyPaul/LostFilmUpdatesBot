<?php
abstract class Parser{
	protected $pageSrc;
	protected $dom;
	protected $pageEncoding;

	public function __construct($pageEncoding = "utf-8"){
		$this->pageEncoding = $pageEncoding;
	}
	
	public function loadSrc($path){
		$this->pageSrc = file_get_contents($path);
		if($this->pageSrc === false)
			throw new Exception('file_get_contents() error');
		
		$this->pageSrc = mb_convert_encoding($this->pageSrc, 'UTF-8', $this->pageEncoding);
	}
	
	abstract public function run();
}
