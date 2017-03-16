<?php

abstract class TracerBase{
	protected $traceName;

	protected function __construct($traceName){
		assert(is_string($traceName));
		$this->traceName = $traceName;

		if(TRACER_LOG_START_END){
			$this->logEvent(
				'[TRACER]',
				__FILE__,
				__LINE__,
				'Trace '.$this->traceName.' has been started'
			);
		}
	}

	public function __destruct(){
		if(TRACER_LOG_START_END){
			$this->logEvent(
				'[TRACER]',
				__FILE__,
				__LINE__,
				'Trace '.$this->traceName.' has been ended'
			);
		}
	}

	abstract protected function writeEvent($text);
	abstract protected function writeError($text);

	private static function compileRecord($tag, $file, $line, $message){
		assert(is_string($tag));
		assert(is_string($file));
		assert(is_int($line));
		assert($message === null || is_string($message));

		$date = date('Y.m.d H:i:s');
		
		// basename should never fail on any input
		$record = "$tag $date ".basename($file).":$line";		
		
		if($message !== null){
			$record .= "\t$message";
		}
		
		return $record;
	}

	public function logEvent($tag, $file, $line, $message = null){
		$record = self::compileRecord($tag, $file, $line, $message);
		$this->writeEvent($record);
	}

	public function logError($tag, $file, $line, $message = null){
		$record = self::compileRecord($tag, $file, $line, $message);
		$this->writeError($record);
	}

	public function logException($tag, Exception $exception){
		return $this->logError(
			$tag,
			$exception->getFile(),
			$exception->getLine(),
			$exception->getMessage()
		);
	}
}
