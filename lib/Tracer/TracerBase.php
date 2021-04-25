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
        string $file,
        int $line,
        string $message
	);

	private function logWrapper(	
		int $level,
		string $file,
		int	$line,
		?string $message
	){
		if($level <= $this->config->getLoggingLevel()){
			$levelStr = TracerLevel::getNameByLevel($level);

			$this->log(
                $levelStr,
                $file,
                $line,
                $message
			);
		}
		
		if($this->secondTracer !== null){
			$this->secondTracer->logWrapper(
				$level,
				$file,
				$line,
				$message
			);
		}
	}

	private function logf(
		int $level,
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
			$file,
			$line,
			sprintf($format, ...$args)
		);
	}

	# Unformated log*
	public function logCritical($file, $line, $message = null){
		$this->logWrapper(TracerLevel::Critical, $file, $line, $message);
	}

	public function logError($file, $line, $message = null){
		$this->logWrapper(TracerLevel::Error, $file, $line, $message);
	}

	public function logWarning($file, $line, $message = null){
		$this->logWrapper(TracerLevel::Warning, $file, $line, $message);
	}

	public function logNotice($file, $line, $message = null){
		$this->logWrapper(TracerLevel::Notice, $file, $line, $message);
	}

	public function logEvent($file, $line, $message = null){
		$this->logWrapper(TracerLevel::Event, $file, $line, $message);
	}

	public function logDebug($file, $line, $message = null){
		$this->logWrapper(TracerLevel::Debug, $file, $line, $message);
	}

	# Formatted log*
	public function logfCritical($file, $line, $format = null, ...$args){
		$this->logf(TracerLevel::Critical, $file, $line, $format, ...$args);
	}

	public function logfError($file, $line, $format = null, ...$args){
		$this->logf(TracerLevel::Error, $file, $line, $format, ...$args);
	}

	public function logfWarning($file, $line, $format = null, ...$args){
		$this->logf(TracerLevel::Warning, $file, $line, $format, ...$args);
	}

	public function logfNotice($file, $line, $format = null, ...$args){
		$this->logf(TracerLevel::Notice, $file, $line, $format, ...$args);
	}

	public function logfEvent($file, $line, $format = null, ...$args){
		$this->logf(TracerLevel::Event, $file, $line, $format, ...$args);
	}

	public function logfDebug($file, $line, $format = null, ...$args){
		$this->logf(TracerLevel::Debug, $file, $line, $format, ...$args);
	}


	public function logException($file, $line, \Throwable $exception, bool $logTrace = true){
		$this->logfError(
            $file,
            $line,
            '%s, raised from %s:%s, reason: "%s"' . PHP_EOL . "%s",
            get_class($exception),
            basename($exception->getFile()),
            $exception->getLine(),
            $exception->getMessage(),
            $logTrace	? print_r($exception->getTrace(), true)
                        : "<Trace already logged.>"
		);

		if($exception->getPrevious() !== null){
			$this->logException($file, $line, $exception->getPrevious(), false);
		}
	}

}
