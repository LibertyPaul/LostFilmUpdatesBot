<?php
require_once(__DIR__.'/../config/config.php');
require_once(__DIR__.'/../config/stuff.php');
require_once(__DIR__.'/../TelegramBotFactory.php');

function logError($message){
	$log = createOrOpenLogFile(__DIR__.'/../logs/webhookErrorLog.txt');
	$errorTextTemplate = "[ERROR]\t[#DATETIME]\t#MESSAGE\n\n";
	
	$errorText = str_replace(
		array('#DATETIME', '#MESSAGE'),
		array(date('d.m.Y H:i:s'), $message),
		$errorTextTemplate
	);
	echo $errorText;
	assert(fwrite($log, $errorText));
	assert(fclose($log));
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
	
$debugOutput = true;
if($debugOutput){
	$log = createOrOpenLogFile(__DIR__.'/../logs/webhookInput.json');
	$readableJson = json_encode($update, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK);
	assert(fwrite($log, "[".date('d.m.Y H:i:s')."]\t".$readableJson."\n"."\n"."\n"));
	assert(fclose($log));
}

if(isset($_GET['ignore_msg_id']) && $_GET['ignore_msg_id'] === 'true'){
	if(intval($update->update_id) < getLastRecievedId()){
		exit('outdated message');
	}
	else{
		setLastRecievedId($update->update_id);
	}
}

if(isset($update->message) === false){
	exit('no message provided in update');
}

$telegram_id = intval($update->message->from->id);

$botFactory = new TelegramBotFactory();
$botFactory->createBot($telegram_id)->incomingUpdate($update->message);





