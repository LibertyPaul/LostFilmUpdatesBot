<?php
require_once(__DIR__."/../config/config.php");
require_once(__DIR__."/../config/stuff.php");
require_once(__DIR__."/../TelegramBot.php");

function error_handler($errno, $errstr, $errfile, $errline, $errcontext){
	$path = __DIR__."/../logs/webhookErrorLog.txt";
	$log = createOrOpenLogFile($path);
	
	$errorText = "[".date('d.m.Y H:i:s')."]\t$errno $errstr $errfile:$errline\n\n";
	fwrite($log, $errorText);
	fclose($log);
}

set_error_handler('error_handler');

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

$update_json = file_get_contents("php://input");
$update = json_decode($update_json);
if($update === null || $update === false){
	exit('incorrect JSON input');
}
	
$debugOutput = true;
if($debugOutput){
	$log = createOrOpenLogFile(__DIR__.'/../logs/webhookInput.json');
	$readableJson = json_encode($update, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK);
	fwrite($log, "[".date('d.m.Y H:i:s')."]\t".$readableJson."\n"."\n"."\n");
	fclose($log);
}

if(isset($_GET['ignore_msg_id']) && $_GET['ignore_msg_id'] === 'true'){
	if(intval($update->update_id) < getLastRecievedId()){
		exit("outdated message");
	}
	else{
		setLastRecievedId($update->update_id);
	}
}

if(isset($update->message) === false){
	exit("no message provided in update");
}

$bot = new TelegramBot(intval($update->message->from->id), intval($update->message->chat->id));
$bot->incomingUpdate($update->message);



