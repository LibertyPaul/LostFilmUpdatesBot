<?php

namespace ErrorHandler;

require_once(__DIR__.'/Tracer/TracerFactory.php');

function error_handler($errno, $errstr, $errfile = null, $errline = null, $errcontext = null){
	static $tracer;
	if(isset($tracer) === false){
		$tracer = \TracerFactory::getTracer(__NAMESPACE__, null, true, false);
	}

	$tracer->logError(
		'[PHP ERROR]', $errfile, $errline,
		sprintf('Errno=[%d], Description: %s', $errno, $errstr)
	);

	$tracer->logError('[o]', $errfile, $errline, print_r(debug_backtrace(), true));

	return true;
}

$res = set_error_handler('ErrorHandler\error_handler');
if($res === null){
	\TracerCompiled::syslogCritical(
		'[ERROR CATCHER]', __FILE__, __LINE__,
		'Unable to set error handler'
	);
}


