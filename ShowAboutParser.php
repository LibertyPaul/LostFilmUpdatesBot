<?php
require_once(__DIR__.'/Parser.php');
require_once(__DIR__.'/Tracer.php');

class ShowAboutParser extends Parser{
	private $tracer;

	public function __construct(HTTPRequesterInterface $requester, $pageEncoding = "utf-8"){
		parent::__construct($requester, $pageEncoding);

		$this->tracer = new Tracer(__CLASS__);
	}

	public function run(){
		$regexp = '/Статус: ([^<]*)/';
		$params = array();
		$res = preg_match_all($regexp, $this->pageSrc, $params);
		if($res === false){
			$this->tracer->log('[REGEXP ERROR]', __FILE__, __LINE__, 'preg_match_all has failed with code '.preg_last_error());
			$this->tracer->log('[REGEXP ERROR]', __FILE__, __LINE__, PHP_EOL.$this->pageSrc);
			throw new Exception('Show info parsing error');
		}
		
		if($res === 0){
			$this->tracer->log('[LOSTFILM ERROR]', __FILE__, __LINE__, 'Status tag wasn\'t found on show page');
			$this->tracer->log('[LOSTFILM ERROR]', __FILE__, __LINE__, PHP_EOL.$this->pageSrc);
			throw new Exception('Show onAir status wasn\'t found');
		}
		
		switch($params[1][0]){
			case 'закончен':
				return false;
			case 'снимается';
				return true;
			default:
				$this->tracer->log('[LOSTFILM ERROR]', __FILE__, __LINE__, 'Incorrect Status tag: '.$params[1][0]);
				$this->tracer->log('[LOSTFILM ERROR]', __FILE__, __LINE__, PHP_EOL.$this->pageSrc);
				throw new Exception('unknown parameter: '.$params[1][0]);
		}
	}
}
