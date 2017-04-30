<?php
namespace ErrorHandler;

require_once(__DIR__.'/Tracer/Tracer.php');

function error_handler($errno, $errstr, $errfile = null, $errline = null, $errcontext = null){
	static $tracer;
	if(isset($tracer) === false){
		$tracer = new \Tracer(__NAMESPACE__);
	}

	$tracer->logError('[PHP ERROR]', $errfile, $errline, "($errno)\t$errstr");

	return true;
}

$res = set_error_handler('ErrorHandler\error_handler');
if($res === null){
	$res = set_error_handler('ErrorHandler\error_handler');
	if($res === null){
		TracerBase::syslogCritical('[ERROR CATCHER]', __FILE__, __LINE__, 'Unable to set error handler');
	}
}


