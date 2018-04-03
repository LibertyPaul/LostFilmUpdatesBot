<?php

require_once(__DIR__.'/TracerLevel.php');
require_once(__DIR__.'/TracerConfig.php');



abstract class TracerBase{
	const CONFIG_INI_PATH = __DIR__.'/TracerConfig.ini';
	protected $config;
	protected $traceName;
	private $secondTracer;

	protected function __construct(
		$traceName,
		TracerBase $secondTracer = null,
		TracerConfig $config = null
	){
		assert(is_string($traceName));
		if($traceName[0] === '-'){
			$traceName = substr($traceName, 1);
		}

		$traceName = str_replace('\\', '.', $traceName);

		$this->traceName = $traceName;
		$this->secondTracer = $secondTracer;
		if($config === null){
			$this->config = new TracerConfig(self::CONFIG_INI_PATH, $traceName);
		}
		else{
			$this->config = $config;
		}
			
		if($this->config->getLogStartedFinished()){
			$this->logDebug('[TRACER]', __FILE__, __LINE__, 'Started.');
		}

	}

	public function __destruct(){
		if($this->config->getLogStartedFinished()){
			$this->logDebug('[TRACER]', __FILE__, __LINE__, 'Finished.');
		}
	}

	abstract protected function write($text);
	abstract protected function storeStandalone($text);

	private static function getDateMicro(){
		# PHP can't into microseconds. Let's help him.
		sscanf(microtime(), '0.%d %d', $microseconds, $seconds);
		$microseconds /= 100;
		return sprintf('%s.%06d', date('Y.m.d H:i:s'), $microseconds);
	}		

	private static function compileRecord($level, $tag, $file, $line, $message){
		assert(is_string($tag));
		assert(is_string($file));
		assert(is_int($line));
		
		return sprintf(
			"%s %s %s [%' 5d] %s:%s %s",
			$level,
			$tag,
			self::getDateMicro(),
			getmypid(),
			basename($file),
			$line,
			$message
		);
	}

	private function log($level, $tag, $file, $line, $message){
		if(empty($message)){
			$message = 'No message provided.';
		}

		if($level <= $this->config->getLoggingLevel()){
			$messageLevel = TracerLevel::getNameByLevel($level);
			$record = self::compileRecord($messageLevel, $tag, $file, $line, $message);
			
			if(strlen($record) > $this->config->getStandaloneIfLargerThan()){
				try{
					$this->storeStandalone($record);
				}
				catch(\RuntimeException $ex){
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
		$this->log(TracerLevel::Critical, $tag, $file, $line, $message);
	}

	public function logError($tag, $file, $line, $message = null){
		$this->log(TracerLevel::Error, $tag, $file, $line, $message);
	}

	public function logWarning($tag, $file, $line, $message = null){
		$this->log(TracerLevel::Warning, $tag, $file, $line, $message);
	}

	public function logNotice($tag, $file, $line, $message = null){
		$this->log(TracerLevel::Notice, $tag, $file, $line, $message);
	}

	public function logEvent($tag, $file, $line, $message = null){
		$this->log(TracerLevel::Event, $tag, $file, $line, $message);
	}

	public function logDebug($tag, $file, $line, $message = null){
		$this->log(TracerLevel::Debug, $tag, $file, $line, $message);
	}

	public function logException($tag, $file, $line, \Throwable $exception = null){
		if($exception !== null){
			$description = sprintf(
				'%s, raised from %s:%s, reason: "%s"',
				get_class($exception),
				basename($exception->getFile()),
				$exception->getLine(),
				$exception->getMessage()
			);
		}
		else{
			$description = 'NULL EXCEPTION WAS PASSED TO logException';
		}

		$this->logError($tag, $file, $line, $description);
	}

	public static function syslogCritical($tag, $file, $line, $message = null){
		if(empty($message)){
			$message = 'No message provided.';
		}

		$record = self::compileRecord(TracerLevel::Critical, $tag, $file, $line, $message);
		assert(syslog(LOG_CRIT, $record));
	}
}
