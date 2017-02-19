<?php
require_once(__DIR__.'/../config/config.php');
require_once(__DIR__.'/../config/stuff.php');
require_once(__DIR__.'/../UpdateHandler.php');

require_once(__DIR__.'/../Tracer.php');
require_once(__DIR__.'/../EchoTracer.php');

$tracer = null;
try{
	$tracer = new Tracer('Webhook');
}
catch(Exception $ex){
	$tracer = new EchoTracer();
	$tracer->logException('[TRACER CRITICAL]'. $ex);
}

function exception_handler($ex){
	global $tracer;
	$tracer->logException('[ERROR]', $ex);
}


function error_handler($errno, $errstr, $errfile, $errline, $errcontext){
	global $tracer;
	$tracer->log('[ERROR]', $errfile, $errline, "($errno)\t$errstr");
}

set_error_handler('error_handler');
set_exception_handler('exception_handler');

$update_json = file_get_contents('php://input');

if(isset($_GET['password']) === false){
	$tracer->log('[ERROR]', __FILE__, __LINE__, 'No password provided');
	$tracer->log('[ERROR]', __FILE__, __LINE__, PHP_EOL.print_r($_REQUEST, true));
	exit('no password provided');
}
elseif($_GET['password'] !== WEBHOOK_PASSWORD){
	exit('incorrect password');
}

$update = json_decode($update_json);
if($update === null || $update === false){
	exit('incorrect JSON input');
}
	
$readableJson = json_encode($update, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK);
$tracer->log('[INCOMING MESSAGE]', __FILE__, __LINE__, PHP_EOL.$readableJson);


$botFactory = new TelegramBotFactory();
$updateHandler = new UpdateHandler($botFactory);
$updateHandler->handleUpdate($update);




