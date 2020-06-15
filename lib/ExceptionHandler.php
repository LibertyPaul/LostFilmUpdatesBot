<?php
namespace ExceptionHandler;

require_once(__DIR__.'/Tracer/TracerFactory.php');

function exception_handler(\Throwable $ex){
	static $tracer;
	if(isset($tracer) === false){
		$tracer = \TracerFactory::getTracer(__NAMESPACE__, null, true, false);
	}

	$tracer->logException('[UNCAUGHT EXCEPTION]', __FILE__, __LINE__, $ex);

	exit;
}

$res = set_exception_handler('ExceptionHandler\exception_handler');
if($res === null){
	\TracerCompiled::syslogCritical(
		'[EXCEPTION CATCHER]', __FILE__, __LINE__,
		'Unable to set exception handler'
	);
}

