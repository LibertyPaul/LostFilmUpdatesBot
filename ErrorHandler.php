<?php
namespace ErrorHandler;

require_once(__DIR__.'/Tracer.php');

function error_handler($errno, $errstr, $errfile = null, $errline = null, $errcontext = null){
	static $tracer;
	if(isset($tracer) === false){
		$tracer = new \Tracer(__NAMESPACE__);
	}

	$tracer->logError('[PHP]', $errfile, $errline, "($errno)\t$errstr");

	return true;
}

set_error_handler('ErrorHandler\error_handler');


