<?php

require_once(__DIR__.'/TracerLevel.php');
require_once(__DIR__.'/TracerConfig.php');

abstract class TracerBase{
	protected $config;
	private $secondTracer;

	protected function __construct(
		TracerConfig $config,
		TracerBase $secondTracer = null
	){
		$this->config = $config;
		$this->secondTracer = $secondTracer;
	}

	protected function __destruct(){

	}

	abstract protected function log(
		string $level,
		string $tag,
		string $file,
		int $line,
		string $message
	);

	private function logWrapper(	
		int $level,
		string $tag,
		string $file,
		int	$line,
		?string $message
	){
		if($level <= $this->config->getLoggingLevel()){
			$levelStr = TracerLevel::getNameByLevel($level);

			$this->log(
				$levelStr,
				$tag,
				$file,
				$line,
				$message
			);
		}
		
		if($this->secondTracer !== null){
			$this->secondTracer->logWrapper(
				$level,
				$tag,
				$file,
				$line,
				$message
			);
		}
	}

	private function logf(
		int $level,
		string $tag,
		string $file,
		int	$line,
		string $format = null,
		...$args
	){
		if($format === null){
			$format = '';
		}
		
		$this->logWrapper(
			$level,
			$tag,
			$file,
			$line,
			sprintf($format, ...$args)
		);
	}

	# Unformated log*
	public function logCritical($tag, $file, $line, $message = null){
		$this->logWrapper(TracerLevel::Critical, $tag, $file, $line, $message);
	}

	public function logError($tag, $file, $line, $message = null){
		$this->logWrapper(TracerLevel::Error, $tag, $file, $line, $message);
	}

	public function logWarning($tag, $file, $line, $message = null){
		$this->logWrapper(TracerLevel::Warning, $tag, $file, $line, $message);
	}

	public function logNotice($tag, $file, $line, $message = null){
		$this->logWrapper(TracerLevel::Notice, $tag, $file, $line, $message);
	}

	public function logEvent($tag, $file, $line, $message = null){
		$this->logWrapper(TracerLevel::Event, $tag, $file, $line, $message);
	}

	public function logDebug($tag, $file, $line, $message = null){
		$this->logWrapper(TracerLevel::Debug, $tag, $file, $line, $message);
	}

	# Formatted log*
	public function logfCritical($tag, $file, $line, $format = null, ...$args){
		$this->logf(TracerLevel::Critical, $tag, $file, $line, $format, ...$args);
	}

	public function logfError($tag, $file, $line, $format = null, ...$args){
		$this->logf(TracerLevel::Error, $tag, $file, $line, $format, ...$args);
	}

	public function logfWarning($tag, $file, $line, $format = null, ...$args){
		$this->logf(TracerLevel::Warning, $tag, $file, $line, $format, ...$args);
	}

	public function logfNotice($tag, $file, $line, $format = null, ...$args){
		$this->logf(TracerLevel::Notice, $tag, $file, $line, $format, ...$args);
	}

	public function logfEvent($tag, $file, $line, $format = null, ...$args){
		$this->logf(TracerLevel::Event, $tag, $file, $line, $format, ...$args);
	}

	public function logfDebug($tag, $file, $line, $format = null, ...$args){
		$this->logf(TracerLevel::Debug, $tag, $file, $line, $format, ...$args);
	}


	public function logException($tag, $file, $line, \Throwable $exception, bool $logTrace = true){
		$this->logfError(
			$tag,
			$file,
			$line,
			'%s, raised from %s:%s, reason: "%s"'.PHP_EOL."%s",
			get_class($exception),
			basename($exception->getFile()),
			$exception->getLine(),
			$exception->getMessage(),
			$logTrace	? print_r($exception->getTrace(), true)
						: "<Trace already logged.>"
		);

		if($exception->getPrevious() !== null){
			$this->logException($tag, $file, $line, $exception->getPrevious(), false);
		}
	}

}
