<?php

abstract class TracerBase{
	protected $traceName;

	protected function __construct($traceName){
		assert(is_string($traceName));

		$this->traceName = $traceName;

		$this->log('[TRACER]', __FILE__, __LINE__, 'Trace '.$this->traceName.' has been started');
	}

	public function __destruct(){
		$this->log('[TRACER]', __FILE__, __LINE__, 'Trace '.$this->traceName.' has been ended');
	}

	abstract protected function write($text);

	public function log($tag, $file, $line, $message = null){
		assert(is_string($tag));
		assert(is_string($file));
		assert(is_int($line));
		assert($message === null || is_string($message));

		$date = date('Y.m.d H:i:s');

		$text = "$tag\t$date\t".basename($file).":$line";
		if($message !== null){
			$text .= "\t$message";
		}

		$this->write($text);
	}

	public function logException($tag, Exception $exception){
		return $this->log($tag, $exception->getFile(), $exception->getLine(), $exception->getMessage());
	}
}
