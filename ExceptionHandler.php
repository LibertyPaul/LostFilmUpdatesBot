<?php
namespace ExceptionHandler;

require_once(__DIR__.'/Tracer.php');

function exception_handler($errno, $errstr, $errfile, $errline, $errcontext){
	static $tracer;
	if(isset($tracer) === false){
		$tracer = new \Tracer(__NAMESPACE__);
	}

	$tracer->logError('[PHP]', $errfile, $errline, "($errno)\t$errstr");
}

set_exception_handler('ExceptionHandler\exception_handler');
