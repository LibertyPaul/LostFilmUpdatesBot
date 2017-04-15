<?php

abstract class TracerLevel{
	const Critical		= 0;
	const Error			= 1;
	const Warning		= 4;
	const Notice		= 6;
	const Event			= 8;
	const Debug			= 9;

	private static $levelMap = array(
		'CRITICAL'	=> self::Critical,
		'ERROR'		=> self::Error,
		'WARNING'	=> self::Warning,
		'NOTICE'	=> self::Notice,
		'EVENT'		=> self::Event,
		'DEBUG'		=> self::Debug
	);

//	private static $codeMap = array_flip(self::$levelMap);
	private static $codeMap = array(
		self::Critical	=> 'CRITICAL',
		self::Error		=> 'ERROR',
		self::Warning	=> 'WARNING',
		self::Notice	=> 'NOTICE',
		self::Event		=> 'EVENT',
		self::Debug		=> 'DEBUG'
	);

	public static function logEverythingLevel(){
		return self::Event;
	}

	public static function getLevelByName($name){
		if(array_key_exists($name, self::$levelMap)){
			return self::$levelMap[$name];
		}
		else{
			throw new OutOfBoundsException("Invalid trace level name: '$name'");
		}
	}

	public static function getNameByLevel($level){
		if(array_key_exists($level, self::$codeMap)){
			return self::$codeMap[$level];
		}
		else{
			throw new OutOfBoundsException("Invalid trace level: '$level'");
		}
	}
}

abstract class TracerBase{
	protected $traceName;
	private $maxLevel;
	private $secondTracer;

	protected function __construct($traceName, TracerBase $secondTracer = null){
		assert(is_string($traceName));
		$this->traceName = $traceName;
		$this->secondTracer = $secondTracer;
			
		if(defined('TRACER_LEVEL')){
			$this->maxLevel = TracerLevel::getLevelByName(TRACER_LEVEL);
		}
		else{
			$this->maxLevel = TracerLevel::logEverythingLevel();
			$this->log('WARNING', '[TRACER]', __FILE__, __LINE__, 'TRACER_LEVEL is not set. Logging everything.');
		}


		$this->logDebug('[TRACER]', __FILE__, __LINE__, 'Started.');

	}

	public function __destruct(){
		$this->logDebug('[TRACER]', __FILE__, __LINE__, 'Ended.');
	}

	abstract protected function write($text);

	protected function storeStandalone($text){
		$this->write($text); // default behavior for case we don't want to use this feature
	}

	private static function compileRecord($level, $tag, $file, $line, $message){
		assert(is_string($tag));
		assert(is_string($file));
		assert(is_int($line));
		assert($message === null || is_string($message));

		$date = date('Y.m.d H:i:s');
		
		// basename should never fail on any input
		$record = "$level\t$tag $date ".basename($file).":$line";		
		
		if($message !== null){
			$record .= "\t$message";
		}
		
		return $record;
	}

	public function log($level, $tag, $file, $line, $message = null){
		$messageLevel = TracerLevel::getLevelByName($level);

		if($messageLevel <= $this->maxLevel){
			$record = self::compileRecord($level, $tag, $file, $line, $message);
			
			if(defined('TRACER_COPMPESSION_BOUND') && strlen($record) > TRACER_COPMPESSION_BOUND){
				try{
					$this->storeStandalone($record);
				}
				catch(RuntimeException $ex){
					$this->logException('[TRACER]', $ex);
					$this->write($record);
				}	
			}
			else{
				$this->write($record);
			}
		}

		if($this->secondTracer !== null){
			$this->secondTracer->log($level, $tag, $file, $line, $message);
		}
	}

	public function logCritical($tag, $file, $line, $message = null){
		$this->log('CRITICAL', $tag, $file, $line, $message);
	}

	public function logError($tag, $file, $line, $message = null){
		$this->log('ERROR', $tag, $file, $line, $message);
	}

	public function logWarning($tag, $file, $line, $message = null){
		$this->log('WARNING', $tag, $file, $line, $message);
	}

	public function logEvent($tag, $file, $line, $message = null){
		$this->log('EVENT', $tag, $file, $line, $message);
	}

	public function logNotice($tag, $file, $line, $message = null){
		$this->log('NOTICE', $tag, $file, $line, $message);
	}

	public function logDebug($tag, $file, $line, $message = null){
		$this->log('DEBUG', $tag, $file, $line, $message);
	}

	public function logException($tag, Exception $exception){
		$this->logError($tag, $exception->getFile(), $exception->getLine(), $exception->getMessage());
	}

	public static function syslogCritical($tag, $file, $line, $message = null){
		$record = self::compileRecord('CRITICAL', $tag, $file, $line, $message);
		assert(syslog(LOG_CRIT, $record));
	}
}
