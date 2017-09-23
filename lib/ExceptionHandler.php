<?php
namespace ExceptionHandler;

require_once(__DIR__.'/Tracer/Tracer.php');

function exception_handler($ex){
	static $tracer;
	if(isset($tracer) === false){
		$tracer = new \Tracer(__NAMESPACE__);
	}

	$tracer->logException('[UNCAUGHT EXCEPTION]', __FILE__, __LINE__, $ex);

	exit;
}

$res = set_exception_handler('ExceptionHandler\exception_handler');
if($res === null){
	$res = set_exception_handler('ExceptionHandler\exception_handler');
	if($res === null){// yep, this is one proper way to check for success
		TracerBase::syslogCritical('[EXCEPTION CATCHER]', __FILE__, __LINE__, 'Unable to set exception handler');
	}
}

