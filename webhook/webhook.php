<?php
require_once(__DIR__.'/../config/config.php');
require_once(__DIR__.'/../config/stuff.php');
require_once(__DIR__.'/../TelegramBotFactory.php');

require_once(__DIR__.'/../Tracer.php');
require_once(__DIR__.'/../EchoTracer.php');

require_once(__DIR__.'/../HTTPRequester.php');

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


function setLastRecievedId($value){
	$memcache = createMemcache();
	$memcache->set(MEMCACHE_LATEST_UPDATE_ID_KEY, intval($value));
}

function getLastRecievedId(){
	$memcache = createMemcache();
	return intval($memcache->get(MEMCACHE_LATEST_UPDATE_ID_KEY));
}

if(isset($_GET['password']) === false){
	exit('no password provided');
}
elseif($_GET['password'] !== WEBHOOK_PASSWORD){
	exit('incorrect password');
}

$update_json = file_get_contents('php://input');
$update = json_decode($update_json);
if($update === null || $update === false){
	exit('incorrect JSON input');
}
	
$readableJson = json_encode($update, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK);
$tracer->log('[INCOMING MESSAGE]', __FILE__, __LINE__, PHP_EOL.$readableJson);

if((isset($_GET['ignore_msg_id']) === false || $_GET['ignore_msg_id'] !== 'true') && IGNORE_UPDATE_ID === false){
	if(intval($update->update_id) < getLastRecievedId()){
		exit('outdated message');
	}
	setLastRecievedId($update->update_id);
}

if(isset($update->message) === false){
	exit('no message provided in update');
}

$telegram_id = intval($update->message->from->id);

$botFactory = new TelegramBotFactory();
$botFactory->createBot($telegram_id)->incomingUpdate($update->message);



if(defined('MESSAGE_STREAM_URL')){
	try{
		$testStream = new HTTPRequester();
		$url = MESSAGE_STREAM_URL.'?password='.MESSAGE_STREAM_PASSWORD;
		$testStream->sendJSONRequest($url, $update_json);
	}
	catch(Exception $ex){

	}
}

