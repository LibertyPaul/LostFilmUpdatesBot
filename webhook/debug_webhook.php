<?php
require_once(__DIR__."/../config/config.php");
require_once(__DIR__."/../config/stuff.php");
require_once(__DIR__."/../TelegramBot.php");
require_once(__DIR__."/input_debug_webhook.php");

function logError($message){
	$log = createOrOpenLogFile(__DIR__."/../logs/webhookErrorLog.txt");
	$errorTextTemplate = "[ERROR]\t#DATETIME]\t#MESSAGE\n\n";
	
	$errorText = str_replace(
		array('#DATETIME', '#MESSAGE'),
		array(date('d.m.Y H:i:s'), $message),
		$errorTextTemplate
	);
	echo $errorText;
	fwrite($log, $errorText);
	fclose($log);
}

function exception_handler($ex){
	logError($ex->getMessage());
}


function error_handler($errno, $errstr, $errfile, $errline, $errcontext){
	logError("$errno $errstr $errfile:$errline");
}

set_error_handler('error_handler');
set_exception_handler('exception_handler');


function setLastRecievedId($value){
	$memcache = createMemcache();
	$memcache->set(MEMCACHE_LATEST_UPDATE_ID_KEY, $value);
}

function getLastRecievedId(){
	$memcache = createMemcache();
	return $memcache->get(MEMCACHE_LATEST_UPDATE_ID_KEY);
}

/*
if($_GET['token'] !== TELEGRAM_BOT_TOKEN)
	exit('incorrect token');
*/
//$update_json = file_get_contents("php://input");




$update = json_decode($update_json);
if($update === null || $update === false)
	throw new Exception("incorrect JSON input");
	
$debugOutput = true;
if($debugOutput){
	$log = createOrOpenLogFile(__DIR__.'/../logs/webhookInput.json');
	$readableJson = json_encode($update, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK);
	fwrite($log, "[".date('d.m.Y H:i:s')."]\t".$readableJson."\n"."\n"."\n");
	fclose($log);
}
/*
if(getLastRecievedId() >= $update->update_id)
	exit;
else
	setLastRecievedId($update->update_id);
*/

if(isset($update->message) === false)
	throw new Exception("no message provided in update");

if(isset($update->message->text) === false)
	throw new Exception("message without text");

$bot = new TelegramBot(intval($update->message->from->id), intval($update->message->chat->id));

$bot->incomingUpdate($update->message);



