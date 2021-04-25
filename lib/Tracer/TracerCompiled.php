<?php

require_once(__DIR__.'/TracerBase.php');

abstract class TracerCompiled extends TracerBase{
	protected function __construct(
		TracerConfig $config,
		TracerBase $secondTracer = null
	){
		parent::__construct(
			$config,
			$secondTracer
		);
	}

	protected function __destruct(){
		parent::__destruct();
	}

	abstract protected function write(string $text);
	abstract protected function storeStandalone(string $text);

	private static function getDateMicro(): string {
		# PHP can't into microseconds. Let's help it.
		sscanf(microtime(), '0.%d %d', $microseconds, $seconds);
		$microseconds /= 100;
		return sprintf('%s.%06d', date('Y.m.d H:i:s'), $microseconds);
	}

	private static function compileRecord(
		string $level,
        string $file,
        int $line,
        string $message
	): string {
		return sprintf(
			"%s %s [%' 5d] %s:%s %s",
			$level,
			self::getDateMicro(),
			getmypid(),
			basename($file),
			$line,
			$message
		);
	}

	protected function log(
		string $level,
        string $file,
        int	$line,
        string $message = null
	){
		if(empty($message)){
			$message = '~|___0^0___|~';
		}

		$record = self::compileRecord($level, $file, $line, $message);
		
		if(strlen($record) > $this->config->getStandaloneIfLargerThan()){
			try{
				$this->storeStandalone($record);
			}
			catch(\RuntimeException $ex){
				$this->logException(__FILE__, __LINE__, $ex);
				$this->write($record);
			}	
		}
		else{
			$this->write($record);
		}
	}

	public static function syslogCritical($file, $line, $format = null, ...$args){
		if(empty($format)){
			$message = 'No message provided.';
		}
		else{
			$message = sprintf($format, ...$args);
		}

		$record = self::compileRecord(
            TracerLevel::Critical,
            $file,
            $line,
            $message
		);

		assert(syslog(LOG_CRIT, $record));
	}
}
